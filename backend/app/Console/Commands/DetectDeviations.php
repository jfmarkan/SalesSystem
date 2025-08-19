<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Assignment;

class DetectDeviations extends Command
{
    protected $signature = 'deviations:detect {--user_id=}';
    protected $description = 'Detect deviations per user & profit center (Forecast vs Budget next 6 months, Sales vs Budget previous month)';

    public function handle(): int
    {
        $now = Carbon::now();

        // Keep your original early-month skip
        if ($now->day < 4) {
            $this->info('Skipping: day < 4.');
            return self::SUCCESS;
        }

        $onlyUserId = $this->option('user_id') ? (int)$this->option('user_id') : null;

        // Users with assignments
        $userIds = Assignment::when($onlyUserId, fn($q) => $q->where('user_id', $onlyUserId))
            ->distinct()->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('No users with assignments.');
            return self::SUCCESS;
        }

        foreach ($userIds as $userId) {
            // Profit center codes for the user
            $pcCodes = Assignment::query()
                ->join('client_profit_centers as cpc', 'cpc.id', '=', 'assignments.client_profit_center_id')
                ->where('assignments.user_id', $userId)
                ->distinct()
                ->pluck('cpc.profit_center_code');

            foreach ($pcCodes as $pcCode) {
                // CPC IDs of the user for this profit center
                $cpcIds = Assignment::query()
                    ->join('client_profit_centers as cpc', 'cpc.id', '=', 'assignments.client_profit_center_id')
                    ->where('assignments.user_id', $userId)
                    ->where('cpc.profit_center_code', $pcCode)
                    ->pluck('assignments.client_profit_center_id')
                    ->all();

                if (!$cpcIds) continue;

                // A) FORECAST vs BUDGET (current month + next 5) => aggregated window
                $forecastWindow = $this->buildForecastWindowSeries($cpcIds, $now, 6);

                if ($forecastWindow['total_budget'] > 0.0) {
                    $ratioF = $forecastWindow['total_forecast'] / $forecastWindow['total_budget'];
                    if ($ratioF < 0.95 || $ratioF > 1.05) {
                        $this->upsertDeviation(
                            profitCenterCode: (int)$pcCode,
                            userId:           (int)$userId,
                            fy:               (int)$now->year,
                            m:                (int)$now->month,
                            type:             'FORECAST',
                            percent:          round($ratioF * 100, 6),
                            sales:            null,
                            budget:           (float)$forecastWindow['total_budget'],
                            forecast:         (float)$forecastWindow['total_forecast'],
                            months:           $forecastWindow['months'],
                            salesSeries:      null,
                            budgetSeries:     $forecastWindow['budget_series'],
                            forecastSeries:   $forecastWindow['forecast_series'],
                        );
                    }
                }

                // B) SALES vs BUDGET (previous month) => single period
                $prev = $now->copy()->subMonth();
                $pm = (int)$prev->month;
                $py = (int)$prev->year;

                $sumSales  = (float) DB::table('sales')
                    ->whereIn('client_profit_center_id', $cpcIds)
                    ->where('fiscal_year', $py)->where('month', $pm)
                    ->sum('volume');

                $sumBudget = (float) DB::table('budgets')
                    ->whereIn('client_profit_center_id', $cpcIds)
                    ->where('fiscal_year', $py)->where('month', $pm)
                    ->sum('volume');

                if ($sumBudget > 0.0 && $sumSales > 0.0) {
                    $ratioS = $sumSales / $sumBudget;
                    if ($ratioS < 0.90 || $ratioS > 1.10) {
                        $this->upsertDeviation(
                            profitCenterCode: (int)$pcCode,
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

    private function buildForecastWindowSeries(array $cpcIds, Carbon $start, int $monthsCount = 6): array
    {
        $months = [];
        $budgetSeries = [];
        $forecastSeries = [];

        $totalBudget = 0.0;
        $totalForecast = 0.0;

        $monthNow = (int)$start->month;
        $yearNow  = (int)$start->year;

        for ($i = 0; $i < $monthsCount; $i++) {
            $m = $monthNow + $i;
            $y = $yearNow;
            if ($m > 12) { $m -= 12; $y += 1; }

            $key = sprintf('%04d-%02d', $y, $m);
            $months[] = $key;

            $b = (float) DB::table('budgets')
                ->whereIn('client_profit_center_id', $cpcIds)
                ->where('fiscal_year', $y)->where('month', $m)
                ->sum('volume');

            $fSum = 0.0;
            foreach ($cpcIds as $cpcId) {
                $ver = DB::table('forecasts')
                    ->where('client_profit_center_id', $cpcId)
                    ->where('fiscal_year', $y)->where('month', $m)
                    ->max('version');

                if ($ver) {
                    $fv = (float) DB::table('forecasts')
                        ->where('client_profit_center_id', $cpcId)
                        ->where('fiscal_year', $y)->where('month', $m)
                        ->where('version', $ver)
                        ->value('volume');
                    $fSum += $fv;
                }
            }

            $budgetSeries[] = $b;
            $forecastSeries[] = $fSum;

            $totalBudget  += $b;
            $totalForecast += $fSum;
        }

        return [
            'months' => $months,
            'budget_series' => $budgetSeries,
            'forecast_series' => $forecastSeries,
            'total_budget' => $totalBudget,
            'total_forecast' => $totalForecast,
        ];
    }

    private function upsertDeviation(
        int $profitCenterCode,
        int $userId,
        int $fy,
        int $m,
        string $type,          // 'FORECAST' | 'SALES'
        float $percent,        // ratio * 100
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
}