<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BudgetingService
{
    /**
     * Genera budget para un FY objetivo.
     * - Proyecta año calendario CY = FY-1 desde ventas YTD (Jan..cutoff) usando seasonality Jan..cutoff.
     * - Aplica best_case y distribuye por seasonality del FY objetivo (Apr..Mar).
     *
     * @return array resumen
     */
    public function generate(int $fiscalYear, bool $fullRebuild = false, ?int $cutoffMonth = null): array
    {
        // cutoff por defecto: mes actual, mínimo octubre (9) como en el VBA
        $now = Carbon::now();
        $cutoff = $cutoffMonth ?: (int)$now->format('n');
        if ($cutoff < 9) $cutoff = 9;
        if ($cutoff > 12) $cutoff = 12;

        $calendarYear = $fiscalYear - 1; // CY = FY-1

        // Opcional: limpiar budgets solo del FY si fullRebuild
        if ($fullRebuild) {
            DB::table('budgets')->where('fiscal_year', $fiscalYear)->delete();
            DB::table('budget_debug_log')->where('fiscal_year', $fiscalYear)->delete();
        }

        // Pre-cargar seasonalities del FY objetivo y del FY-1 por pc_code
        $seasonsCur = $this->loadSeasonalityByPc($fiscalYear);
        $seasonsPrev = $this->loadSeasonalityByPc($fiscalYear - 1);

        // Lista de CPCs a procesar: por ventas en CY Jan..cutoff o todos los CPCs si preferís
        $cpcIds = DB::table('sales')
            ->where(function ($q) use ($calendarYear, $cutoff) {
                // Jan..min(3,cutoff) pertenecen al FY (calendarYear-1)
                $janTo = min(3, $cutoff);
                if ($janTo >= 1) {
                    $q->orWhere(function ($qq) use ($calendarYear, $janTo) {
                        $qq->where('fiscal_year', $calendarYear - 1)
                           ->whereBetween('month', [1, $janTo]);
                    });
                }
                // Apr..cutoff pertenecen al FY (calendarYear) si cutoff>=4
                if ($cutoff >= 4) {
                    $q->orWhere(function ($qq) use ($calendarYear, $cutoff) {
                        $qq->where('fiscal_year', $calendarYear)
                           ->whereBetween('month', [4, $cutoff]);
                    });
                }
            })
            ->distinct()
            ->pluck('client_profit_center_id');

        // Map CPC -> pc_code
        $pcByCpc = DB::table('client_profit_centers as cpc')
            ->join('profit_centers as pc', 'pc.profit_center_code', '=', 'cpc.profit_center_code')
            ->whereIn('cpc.id', $cpcIds)
            ->pluck('pc.profit_center_code', 'cpc.id');

        $inserted = 0;
        $skipped  = 0;
        $updated  = 0;

        DB::beginTransaction();
        try {
            foreach ($pcByCpc as $cpcId => $pcCode) {

                // Ventas YTD para calendario CY Jan..cutoff (mapea a FY-1 y FY)
                $salesYtd = $this->salesYtdCalendar($cpcId, $calendarYear, $cutoff);

                if ($salesYtd <= 0) { $skipped++; continue; }

                // Pct acumulado de Jan..cutoff con seasonality: Jan..Mar de FY-1, Apr..cutoff de FY
                $pctTotal = $this->accumSeasonPctJanToCutoff($pcCode, $seasonsPrev, $seasonsCur, $cutoff);
                if ($pctTotal <= 0) { $skipped++; continue; }

                $baseForecast = $salesYtd / ($pctTotal / 100.0);

                // Best case por CPC para FY objetivo
                $bestCase = (float) DB::table('budget_cases')
                    ->where('client_profit_center_id', $cpcId)
                    ->where('fiscal_year', $fiscalYear)
                    ->value('best_case') ?? 0.0;

                $totalBudget = round($baseForecast * (1.0 + $bestCase / 100.0), 2);

                // Distribuir por seasonality del FY objetivo (Apr..Mar)
                $seasonRow = $seasonsCur[$pcCode] ?? null;
                if (!$seasonRow) { $skipped++; continue; }

                $plan = $this->distributeBySeasonality($totalBudget, $seasonRow);

                // Upsert a budgets + insertar debug
                foreach ($plan as $m => $vol) {
                    $exists = DB::table('budgets')
                        ->where('client_profit_center_id', $cpcId)
                        ->where('fiscal_year', $fiscalYear)
                        ->where('month', $m)
                        ->exists();

                    DB::table('budgets')->updateOrInsert(
                        [
                            'client_profit_center_id' => $cpcId,
                            'fiscal_year'             => $fiscalYear,
                            'month'                   => $m,
                        ],
                        [
                            'volume'     => (int) round($vol),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    // Debug
                    DB::table('budget_debug_log')->insert([
                        'client_profit_center_id' => $cpcId,
                        'fiscal_year'             => $fiscalYear,
                        'budget_year'             => ($m >= 4 ? $fiscalYear : $fiscalYear + 1),
                        'budget_month'            => $m,
                        'month_name'              => $this->monthName3($m),
                        'sales_volume'            => $salesYtd,
                        'best_case'               => $bestCase,
                        'worst_case'              => null,
                        'seasonality_base'        => $pctTotal,
                        'forecast_base'           => $baseForecast,
                        'total_budget'            => $totalBudget,
                        'monthly_pct'             => $this->seasonPctForMonth($seasonRow, $m),
                        'monthly_volume'          => $vol,
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ]);

                    $exists ? $updated++ : $inserted++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'fiscal_year' => $fiscalYear,
            'cutoff'      => $cutoff,
            'calendar'    => $calendarYear,
            'processed'   => count($pcByCpc),
            'inserted'    => $inserted,
            'updated'     => $updated,
            'skipped'     => $skipped,
        ];
    }

    /* ---------- Helpers ---------- */

    protected function loadSeasonalityByPc(int $fiscalYear): array
    {
        $rows = DB::table('seasonalities')
            ->where('fiscal_year', $fiscalYear)
            ->get();

        $by = [];
        foreach ($rows as $r) {
            $by[(int)$r->profit_center_code] = $r;
        }
        return $by;
    }

    protected function salesYtdCalendar(int $cpcId, int $calendarYear, int $cutoff): float
    {
        $sum = 0.0;

        // Jan..min(3,cutoff) => fiscal_year = CY-1, months 1..min(3,cutoff)
        $janTo = min(3, $cutoff);
        if ($janTo >= 1) {
            $sum += (float) DB::table('sales')
                ->where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $calendarYear - 1)
                ->whereBetween('month', [1, $janTo])
                ->sum('volume');
        }

        // Apr..cutoff => fiscal_year = CY, months 4..cutoff
        if ($cutoff >= 4) {
            $sum += (float) DB::table('sales')
                ->where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $calendarYear)
                ->whereBetween('month', [4, $cutoff])
                ->sum('volume');
        }

        return $sum;
    }

    protected function accumSeasonPctJanToCutoff(int $pcCode, array $prev, array $cur, int $cutoff): float
    {
        $rowPrev = $prev[$pcCode] ?? null;
        $rowCur  = $cur[$pcCode] ?? null;
        if (!$rowPrev || !$rowCur) return 0.0;

        $acc = 0.0;
        for ($m = 1; $m <= $cutoff; $m++) {
            if ($m <= 3) {
                $acc += $this->seasonPctForMonth($rowPrev, $m); // Jan..Mar de FY-1
            } else {
                $acc += $this->seasonPctForMonth($rowCur, $m);  // Apr..cutoff de FY
            }
        }
        return $acc;
    }

    protected function seasonPctForMonth(object $seasonRow, int $month): float
    {
        // columnas: apr,may,jun,jul,aug,sep,oct,nov,dec,jan,feb,mar
        static $map = [
            1 => 'jan', 2 => 'feb', 3 => 'mar',
            4 => 'apr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug', 9 => 'sep',
            10 => 'oct', 11 => 'nov', 12 => 'dec',
        ];
        $col = $map[$month] ?? null;
        if (!$col) return 0.0;
        return (float) ($seasonRow->{$col} ?? 0.0);
    }

    protected function distributeBySeasonality(float $total, object $seasonRow): array
    {
        $out = [];
        for ($m = 4; $m <= 12; $m++) {
            $pct = $this->seasonPctForMonth($seasonRow, $m);
            $out[$m] = round(($pct / 100.0) * $total, 2);
        }
        for ($m = 1; $m <= 3; $m++) {
            $pct = $this->seasonPctForMonth($seasonRow, $m);
            $out[$m] = round(($pct / 100.0) * $total, 2);
        }
        return $out;
    }

    protected function monthName3(int $m): string
    {
        return ['','Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'][$m] ?? '???';
    }
}