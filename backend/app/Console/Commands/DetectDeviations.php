<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Assignment;

class DetectDeviations extends Command
{
    protected $signature = 'deviations:detect {--user_id=}';
    protected $description = 'Detect deviations per user & profit center (Forecast vs Budget ventana inteligente FY Apr–Mar, Sales vs Budget mes anterior)';

    /** caches */
    private array $seasonalityCache = [];   // key: hash(cpcIds,fystart) => [1..12] = weight
    private array $assignmentEffCache = []; // key: pc|user|fyStart => float
    private array $factorM3Cache = [];      // key: pcCode => factor_to_m3

    public function handle(): int
    {
        $now = Carbon::now();

        $onlyUserId = $this->option('user_id') ? (int)$this->option('user_id') : null;

        $userIds = Assignment::when($onlyUserId, fn($q) => $q->where('user_id', $onlyUserId))
            ->distinct()->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('No users with assignments.');
            return self::SUCCESS;
        }

        foreach ($userIds as $userId) {
            $pcCodes = Assignment::query()
                ->join('client_profit_centers as cpc', 'cpc.id', '=', 'assignments.client_profit_center_id')
                ->where('assignments.user_id', $userId)
                ->distinct()
                ->pluck('cpc.profit_center_code');

            foreach ($pcCodes as $pcCode) {
                $pcCodeStr = (string)$pcCode;
                $useM3 = $this->usesM3($pcCodeStr);

                $cpcIds = Assignment::query()
                    ->join('client_profit_centers as cpc', 'cpc.id', '=', 'assignments.client_profit_center_id')
                    ->where('assignments.user_id', $userId)
                    ->where('cpc.profit_center_code', $pcCodeStr)
                    ->pluck('assignments.client_profit_center_id')
                    ->all();

                if (!$cpcIds) continue;

                // A) FORECAST vs BUDGET (mes en curso + 5; si cruza FY y el mes no tiene budget -> corto ventana)
                $fw = $this->buildForecastWindowSeries(
                    cpcIds: $cpcIds,
                    pcCode: $pcCodeStr,
                    userId: (int)$userId,
                    start:  $now->copy()->startOfMonth(),
                    monthsCount: 6
                );

                if ($fw['total_budget'] > 0.0) {
                    $ratioF = $fw['total_forecast'] / $fw['total_budget'];
                    if ($ratioF < 0.95 || $ratioF > 1.05) {
                        $this->upsertDeviation(
                            profitCenterCode: (int)$pcCodeStr,
                            userId:           (int)$userId,
                            fy:               (int)$now->year,
                            m:                (int)$now->month,
                            type:             'FORECAST',
                            percent:          round($ratioF * 100, 6),
                            // ventas en VK-EH o m³ según PC
                            sales:            (float)array_sum($fw['sales_series']),
                            budget:           (float)$fw['total_budget'],
                            forecast:         (float)$fw['total_forecast'],
                            months:           $fw['months'],
                            salesSeries:      $fw['sales_series'],
                            budgetSeries:     $fw['budget_series'],
                            forecastSeries:   $fw['forecast_series'],
                        );
                    }
                }

                // B) SALES vs BUDGET (mes anterior)
                $prev = $now->copy()->subMonth();
                $pm = (int)$prev->month;
                $py = (int)$prev->year;

                // SALES
                if ($useM3) {
                    // m³ desde sales.cubic_meters
                    $sumSales = (float) DB::table('sales')
                        ->whereIn('client_profit_center_id', $cpcIds)
                        ->where('fiscal_year', $py)->where('month', $pm)
                        ->sum('cubic_meters');
                } else {
                    // VK-EH desde sales.sales_units
                    $sumSales = (float) DB::table('sales')
                        ->whereIn('client_profit_center_id', $cpcIds)
                        ->where('fiscal_year', $py)->where('month', $pm)
                        ->sum('sales_units');
                }

                // BUDGET base
                if ($useM3) {
                    // budgets.volume * factor_to_m3
                    $sumBudgetBase = (float) DB::table('budgets')
                        ->join('client_profit_centers as cpc', 'cpc.id', '=', 'budgets.client_profit_center_id')
                        ->leftJoin('unit_conversions as uc', 'uc.profit_center_code', '=', 'cpc.profit_center_code')
                        ->whereIn('budgets.client_profit_center_id', $cpcIds)
                        ->where('budgets.fiscal_year', $py)->where('budgets.month', $pm)
                        ->sum(DB::raw('budgets.volume * COALESCE(uc.factor_to_m3,1)'));
                } else {
                    // VK-EH crudo
                    $sumBudgetBase = (float) DB::table('budgets')
                        ->whereIn('client_profit_center_id', $cpcIds)
                        ->where('fiscal_year', $py)->where('month', $pm)
                        ->sum('volume');
                }

                // extra quota mensual (ya viene en VK-EH o m³ según PC)
                $assignmentMonthly = $this->assignmentMonthlyShareFY(
                    pcCode: $pcCodeStr,
                    userId: (int)$userId,
                    cpcIds: $cpcIds,
                    Y:      $py,
                    m:      $pm
                );

                $sumBudget = $sumBudgetBase + $assignmentMonthly;

                if ($sumBudget > 0.0) {
                    $ratioS = ($sumSales >= 0.0) ? ($sumSales / $sumBudget) : 0.0;
                    if ($ratioS < 0.90 || $ratioS > 1.10) {
                        $this->upsertDeviation(
                            profitCenterCode: (int)$pcCodeStr,
                            userId:           (int)$userId,
                            fy:               (int)$py,
                            m:                (int)$pm,
                            type:             'SALES',
                            percent:          round($ratioS * 100, 6),
                            sales:            (float)$sumSales,
                            budget:           (float)$sumBudget,
                            forecast:         null,
                            months:           [sprintf('%04d-%02d', $py, $pm)],
                            salesSeries:      [$sumSales],
                            budgetSeries:     [$sumBudget],
                            forecastSeries:   null
                        );
                    }
                }
            }
        }

        $this->info('Deviation detection finished.');
        return self::SUCCESS;
    }

    private function buildForecastWindowSeries(array $cpcIds, string $pcCode, int $userId, Carbon $start, int $monthsCount = 6): array
    {
        $months = [];
        $budgetSeries = [];
        $forecastSeries = [];
        $salesSeries = [];

        $totalBudget = 0.0;
        $totalForecast = 0.0;

        $useM3 = $this->usesM3($pcCode);

        // incluir mes actual
        $cursor = $start->copy();
        $refFyStart = $this->fyStartFor((int)$cursor->year, (int)$cursor->month);

        for ($i = 0; $i < $monthsCount; $i++) {
            $y = (int)$cursor->year;   // calendario
            $m = (int)$cursor->month;  // 1..12
            $fyStart = $this->fyStartFor($y, $m);

            // ===== BUDGET BASE =====
            if ($useM3) {
                // budgets.volume * factor_to_m3
                $bBase = (float) DB::table('budgets')
                    ->join('client_profit_centers as cpc', 'cpc.id', '=', 'budgets.client_profit_center_id')
                    ->leftJoin('unit_conversions as uc', 'uc.profit_center_code', '=', 'cpc.profit_center_code')
                    ->whereIn('budgets.client_profit_center_id', $cpcIds)
                    ->where('budgets.fiscal_year', $y)->where('budgets.month', $m)
                    ->sum(DB::raw('budgets.volume * COALESCE(uc.factor_to_m3,1)'));
            } else {
                // VK-EH
                $bBase = (float) DB::table('budgets')
                    ->whereIn('client_profit_center_id', $cpcIds)
                    ->where('fiscal_year', $y)->where('month', $m)
                    ->sum('volume');
            }

            // Si cruzo a un FY nuevo y el mes no tiene budget base, corto la ventana
            if ($fyStart > $refFyStart && $bBase <= 0.0) {
                break;
            }

            $months[] = sprintf('%04d-%02d', $y, $m);

            // cuota extra mensual (en VK-EH o m³ según PC)
            $bEqa = $this->assignmentMonthlyShareFY(
                pcCode: $pcCode,
                userId: $userId,
                cpcIds: $cpcIds,
                Y:      $y,
                m:      $m
            );
            $b = $bBase + $bEqa;

            // ===== FORECAST BASE =====
            $lv = DB::table('forecasts')
                ->whereIn('client_profit_center_id', $cpcIds)
                ->where('fiscal_year', $y)->where('month', $m)
                ->where('user_id', $userId)
                ->select('client_profit_center_id','fiscal_year','month','user_id', DB::raw('MAX(version) as max_version'))
                ->groupBy('client_profit_center_id','fiscal_year','month','user_id');

            if ($useM3) {
                // forecasts.volume * factor_to_m3
                $fBase = (float) DB::table('forecasts')
                    ->joinSub($lv, 'lv', function($j){
                        $j->on('forecasts.client_profit_center_id','=','lv.client_profit_center_id')
                          ->on('forecasts.fiscal_year','=','lv.fiscal_year')
                          ->on('forecasts.month','=','lv.month')
                          ->on('forecasts.user_id','=','lv.user_id')
                          ->on('forecasts.version','=','lv.max_version');
                    })
                    ->join('client_profit_centers as cpc', 'cpc.id', '=', 'forecasts.client_profit_center_id')
                    ->leftJoin('unit_conversions as uc', 'uc.profit_center_code', '=', 'cpc.profit_center_code')
                    ->sum(DB::raw('forecasts.volume * COALESCE(uc.factor_to_m3,1)'));
            } else {
                // VK-EH
                $fBase = (float) DB::table('forecasts')
                    ->joinSub($lv, 'lv', function($j){
                        $j->on('forecasts.client_profit_center_id','=','lv.client_profit_center_id')
                          ->on('forecasts.fiscal_year','=','lv.fiscal_year')
                          ->on('forecasts.month','=','lv.month')
                          ->on('forecasts.user_id','=','lv.user_id')
                          ->on('forecasts.version','=','lv.max_version');
                    })
                    ->sum('forecasts.volume');
            }

            // ===== EXTRA QUOTA FORECAST (EQF) =====
            $fEq = $this->extraQuotaForecastForMonthYM(
                pcCode: $pcCode,
                userId: $userId,
                y:      $y,
                m:      $m,
                fyStartForOpp: $fyStart
            );

            // ===== SALES =====
            if ($useM3) {
                // m³ desde sales.cubic_meters
                $s = (float) DB::table('sales')
                    ->whereIn('client_profit_center_id', $cpcIds)
                    ->where('fiscal_year', $y)->where('month', $m)
                    ->sum('cubic_meters');
            } else {
                // VK-EH desde sales.sales_units
                $s = (float) DB::table('sales')
                    ->whereIn('client_profit_center_id', $cpcIds)
                    ->where('fiscal_year', $y)->where('month', $m)
                    ->sum('sales_units');
            }

            $budgetSeries[]   = $b;
            $forecastSeries[] = $fBase + $fEq; // incluye EQF
            $salesSeries[]    = $s;

            $totalBudget  += $b;
            $totalForecast += ($fBase + $fEq);

            $cursor->addMonth();
        }

        return [
            'months'          => $months,
            'budget_series'   => $budgetSeries,
            'forecast_series' => $forecastSeries,
            'sales_series'    => $salesSeries,
            'total_budget'    => $totalBudget,
            'total_forecast'  => $totalForecast,
        ];
    }

    private function extraQuotaForecastForMonthYM(string $pcCode, int $userId, int $y, int $m, int $fyStartForOpp): float
    {
        $useM3 = $this->usesM3($pcCode);

        $eqfLv = DB::table('extra_quota_forecasts')
            ->where('fiscal_year', $y)
            ->where('month', $m)
            ->select('opportunity_group_id', DB::raw('MAX(version) as max_version'))
            ->groupBy('opportunity_group_id');

        $opLv = DB::table('sales_opportunities')
            ->select('opportunity_group_id', DB::raw('MAX(version) as max_version'))
            ->groupBy('opportunity_group_id');

        $base = DB::table('extra_quota_forecasts as eqf')
            ->joinSub($eqfLv, 'lv', function($j){
                $j->on('eqf.opportunity_group_id','=','lv.opportunity_group_id')
                  ->on('eqf.version','=','lv.max_version');
            })
            ->joinSub($opLv, 'oplv', function($j){
                $j->on('oplv.opportunity_group_id','=','eqf.opportunity_group_id');
            })
            ->join('sales_opportunities as op', function($j){
                $j->on('op.opportunity_group_id','=','oplv.opportunity_group_id')
                  ->on('op.version','=','oplv.max_version');
            })
            ->where('op.profit_center_code', $pcCode)
            ->where('op.user_id', $userId)
            ->where('op.fiscal_year', $fyStartForOpp)
            ->whereNotIn('op.status', ['won','lost']);

        if ($useM3) {
            // volumen EQF m³
            $rows = $base
                ->leftJoin('unit_conversions as uc', 'uc.profit_center_code', '=', 'op.profit_center_code')
                ->sum(DB::raw('eqf.volume * COALESCE(uc.factor_to_m3,1)'));
        } else {
            // VK-EH
            $rows = $base->sum('eqf.volume');
        }

        return (float)$rows;
    }

    private function fyStartFor(int $Y, int $m): int
    {
        return ($m >= 4) ? $Y : ($Y - 1);
    }

    private function seasonalityWeightsFY(array $cpcIds, int $fyStart): array
    {
        $key = md5(json_encode([$cpcIds, $fyStart]));
        if (isset($this->seasonalityCache[$key])) return $this->seasonalityCache[$key];

        $rows = DB::table('budgets')
            ->select('fiscal_year', 'month', DB::raw('SUM(volume) as s'))
            ->whereIn('client_profit_center_id', $cpcIds)
            ->where(function ($w) use ($fyStart) {
                $w->where(function ($a) use ($fyStart) {
                    $a->where('fiscal_year', $fyStart)->whereBetween('month', [4, 12]);
                })->orWhere(function ($b) use ($fyStart) {
                    $b->where('fiscal_year', $fyStart + 1)->whereBetween('month', [1, 3]);
                });
            })
            ->groupBy('fiscal_year', 'month')
            ->get();

        $sumByMonth = array_fill(1, 12, 0.0);
        $total = 0.0;
        foreach ($rows as $r) {
            $sumByMonth[(int)$r->month] = (float)$r->s;
            $total += (float)$r->s;
        }

        if ($total <= 0.0) {
            for ($m = 1; $m <= 12; $m++) $sumByMonth[$m] = 1.0 / 12.0;
        } else {
            for ($m = 1; $m <= 12; $m++) $sumByMonth[$m] = $sumByMonth[$m] / $total;
        }

        return $this->seasonalityCache[$key] = $sumByMonth;
    }

    private function assignmentEffectiveTotalFY(string $pcCode, int $userId, int $fyStart): float
    {
        $key = $pcCode . '|' . $userId . '|' . $fyStart;
        if (isset($this->assignmentEffCache[$key])) return $this->assignmentEffCache[$key];

        $assigned = (float) DB::table('extra_quota_assignments')
            ->where('profit_center_code', $pcCode)
            ->where('user_id', $userId)
            ->where('is_published', 1)
            ->where('fiscal_year', $fyStart)
            ->sum('volume');

        $won = (float) DB::table('sales_opportunities')
            ->where('profit_center_code', $pcCode)
            ->where('user_id', $userId)
            ->where('fiscal_year', $fyStart)
            ->where(function($q){
                $q->where('is_won', 1)->orWhere('status', 'won');
            })
            ->sum('volume');

        $effective = max(0.0, $assigned - $won);
        return $this->assignmentEffCache[$key] = $effective;
    }

    private function assignmentMonthlyShareFY(string $pcCode, int $userId, array $cpcIds, int $Y, int $m): float
    {
        $fy = $this->fyStartFor($Y, $m);
        $weights = $this->seasonalityWeightsFY($cpcIds, $fy);
        $w = $weights[$m] ?? (1.0 / 12.0);

        // total asignado efectivo en VK-EH
        $totalUnits = $this->assignmentEffectiveTotalFY($pcCode, $userId, $fy);
        $shareUnits = $totalUnits * $w;

        if ($this->usesM3($pcCode)) {
            // convertimos todo el share a m³
            $factor = $this->factorToM3($pcCode);
            return $shareUnits * $factor;
        }

        return $shareUnits;
    }

    private function extraQuotaForecastOpenForMonth(string $pcCode, int $userId, int $Y, int $m): float
    {
        // OJO: actualmente no se usa en la lógica; lo dejamos en VK-EH.
        $opBase = DB::table('sales_opportunities')
            ->select('opportunity_group_id', DB::raw('MAX(version) as max_version'))
            ->groupBy('opportunity_group_id');

        $eqfBase = DB::table('extra_quota_forecasts')
            ->where('fiscal_year', $Y)
            ->where('month', $m)
            ->select('opportunity_group_id', 'fiscal_year', 'month', DB::raw('MAX(version) as max_version'))
            ->groupBy('opportunity_group_id', 'fiscal_year', 'month');

        $rows = DB::table('extra_quota_forecasts as eqf')
            ->joinSub($eqfBase, 'lv', function($j){
                $j->on('eqf.opportunity_group_id','=','lv.opportunity_group_id')
                  ->on('eqf.fiscal_year','=','lv.fiscal_year')
                  ->on('eqf.month','=','lv.month')
                  ->on('eqf.version','=','lv.max_version');
            })
            ->joinSub($opBase, 'oplv', function($j){
                $j->on('oplv.opportunity_group_id','=','eqf.opportunity_group_id');
            })
            ->join('sales_opportunities as op', function($j){
                $j->on('op.opportunity_group_id','=','oplv.opportunity_group_id')
                  ->on('op.version','=','oplv.max_version');
            })
            ->where('op.profit_center_code', $pcCode)
            ->where('op.user_id', $userId)
            ->where('op.fiscal_year', $this->fyStartFor($Y, $m))
            ->where(function($q){ $q->whereNull('op.is_won')->orWhere('op.is_won', 0); })
            ->where(function($q){ $q->whereNull('op.is_lost')->orWhere('op.is_lost', 0); })
            ->whereNotIn('op.status', ['won','lost'])
            ->get(['eqf.volume as vol', 'op.probability_pct as prob']);

        $sum = 0.0;
        foreach ($rows as $r) {
            $prob = max(0.0, min(100.0, (float)($r->prob ?? 0.0))) / 100.0;
            $sum += ((float)$r->vol) * $prob;
        }
        return $sum;
    }

    private function upsertDeviation(
        int $profitCenterCode,
        int $userId,
        int $fy,
        int $m,
        string $type,
        float $percent,
        ?float $sales = null,
        ?float $budget = null,
        ?float $forecast = null,
        ?array $months = null,
        ?array $salesSeries = null,
        ?array $budgetSeries = null,
        ?array $forecastSeries = null
    ): void {
        $type = strtoupper($type) === 'FORECAST' ? 'FORECAST' : 'SALES';

        $ref = $type === 'FORECAST' ? $forecast : $sales;

        $deltaAbs = (!is_null($budget) && !is_null($ref)) ? ($ref - $budget) : null;
        $deltaPct = (!is_null($budget) && $budget != 0.0 && !is_null($ref))
            ? (($ref - $budget) / $budget) * 100.0
            : null;

        if (!is_null($deltaAbs)) $deltaAbs = round($deltaAbs, 4);
        if (!is_null($deltaPct)) $deltaPct = round($deltaPct, 6);

        $now = Carbon::now();

        $where = [
            'profit_center_code' => $profitCenterCode,
            'fiscal_year'        => $fy,
            'month'              => $m,
            'deviation_type'     => $type,
            'user_id'            => $userId,
        ];

        $existing = DB::table('deviations')->where($where)->first();

        $payload = [
            'profit_center_code' => $profitCenterCode,
            'deviation_type'     => $type,
            'fiscal_year'        => $fy,
            'month'              => $m,
            'user_id'            => $userId,

            'sales'              => $sales,
            'budget'             => $budget,
            'forecast'           => $forecast,
            'delta_abs'          => $deltaAbs,
            'delta_pct'          => $deltaPct,
            'deviation_ratio'    => round($percent, 6),

            'months'             => is_array($months) ? json_encode(array_values($months)) : null,
            'sales_series'       => is_array($salesSeries) ? json_encode(array_values($salesSeries)) : null,
            'budget_series'      => is_array($budgetSeries) ? json_encode(array_values($budgetSeries)) : null,
            'forecast_series'    => is_array($forecastSeries) ? json_encode(array_values($forecastSeries)) : null,

            'updated_at'         => $now,
        ];

        if ($existing) {
            DB::table('deviations')->where($where)->update($payload);
        } else {
            $payload['created_at'] = $now;
            $payload['justified'] = false;
            DB::table('deviations')->insert($payload);
        }
    }

    /* ===================== Helpers PC / m³ ===================== */

    private function pc3(string $pcCode): string
    {
        $s = strtoupper(trim((string)$pcCode));
        return substr($s, 0, 3);
    }

    private function usesM3(string $pcCode): bool
    {
        // PCs que deben trabajar en m³: 110, 170, 171, 175
        return in_array($this->pc3($pcCode), ['110','170','171','175'], true);
    }

    private function factorToM3(string $pcCode): float
    {
        $key = (string)$pcCode;
        if (isset($this->factorM3Cache[$key])) return $this->factorM3Cache[$key];

        $factor = (float) DB::table('unit_conversions')
            ->where('profit_center_code', $pcCode)
            ->max('factor_to_m3');

        if (!$factor || !is_finite($factor)) $factor = 1.0;

        return $this->factorM3Cache[$key] = $factor;
    }
}
