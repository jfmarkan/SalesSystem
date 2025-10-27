<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BudgetGenerationController extends Controller
{
    private const FISCAL_START_MONTH = 4; // Abril

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'best_case_c'   => ['required','numeric','between:-100,100'],
            'worst_case_c'  => ['required','numeric','between:-100,100'],
            'best_case_d'   => ['required','numeric','between:-100,100'],
            'worst_case_d'  => ['required','numeric','between:-100,100'],
            'pa_pb_mode'    => ['required','in:D,AB_BUDGET_CASES,AB_MANUAL'],
            'pa_pb_cases'   => ['nullable','array'], // { cpc_id: {best: float, worst: float}, ... }
        ]);

        $bestC = (float)$validated['best_case_c'];
        $worstC= (float)$validated['worst_case_c'];
        $bestD = (float)$validated['best_case_d'];
        $worstD= (float)$validated['worst_case_d'];
        $paPbMode = $validated['pa_pb_mode'];
        $paPbCases = $validated['pa_pb_cases'] ?? [];

        $now   = Carbon::now();
        $nextFY= $this->nextFiscalYear($now);

        $months = [
            ['name'=>'Apr','m'=>4],['name'=>'May','m'=>5],['name'=>'Jun','m'=>6],
            ['name'=>'Jul','m'=>7],['name'=>'Aug','m'=>8],['name'=>'Sep','m'=>9],
            ['name'=>'Oct','m'=>10],['name'=>'Nov','m'=>11],['name'=>'Dec','m'=>12],
            ['name'=>'Jan','m'=>1], ['name'=>'Feb','m'=>2], ['name'=>'Mar','m'=>3],
        ];

        // CPC activos (aparecen en sales)
        $cpcIds = DB::table('sales')
            ->select('client_profit_center_id')
            ->distinct()
            ->pluck('client_profit_center_id');

        $budgetsCnt = 0;
        $forecastsCnt = 0;

        foreach ($cpcIds as $cpcId) {
            $cpc = DB::table('client_profit_centers')->where('id', $cpcId)->first();
            if (!$cpc) continue;

            $client = DB::table('clients')->where('client_group_number', $cpc->client_group_number)->first(); // :contentReference[oaicite:3]{index=3}
            if (!$client) continue;

            $classId = (int)$client->classification_id; // 1=A,2=B,3=C,4=D,5=X,6=PA,7=PB

            if ($classId === 5) continue; // X: ignorar

            // Seasonality actual y anterior
            $seasonCurr = DB::table('seasonalities')
                ->where('profit_center_code', $cpc->profit_center_code)
                ->orderByDesc('fiscal_year')
                ->first();
            if (!$seasonCurr) continue;

            $seasonPrev = DB::table('seasonalities')
                ->where('profit_center_code', $cpc->profit_center_code)
                ->where('fiscal_year', '<', $seasonCurr->fiscal_year)
                ->orderByDesc('fiscal_year')
                ->first();

            $mapCurr = $this->mapSeasonRow($seasonCurr);
            $mapPrev = $this->mapSeasonRow($seasonPrev ?? $seasonCurr);

            // Base forecast anualizada (VBA-like)
            $base = $this->calculateBaseForecast($cpcId, $mapCurr, $mapPrev);
            if ($base <= 0) continue;

            // % best/worst según clasificación y modo PA/PB
            [$bestPct, $worstPct, $forecastEqualsBudget] = $this->resolvePcts(
                $classId,
                (int)$cpcId,
                (int)$nextFY,
                $bestC, $worstC, $bestD, $worstD,
                $paPbMode,
                $paPbCases
            );

            $totalBest  = $base * (1 + $bestPct  / 100.0);
            $totalWorst = $base * (1 + $worstPct / 100.0);

            $budgetRows = [];
            $forecastRows = [];
            $debugRows = [];

            foreach ($months as $slot) {
                $m  = $slot['m'];
                $fy = ($m >= self::FISCAL_START_MONTH) ? $nextFY : ($nextFY + 1);
                $pct = (float)($mapCurr[$slot['name']] ?? 0.0);

                $budgetVol = (int) round(($pct / 100.0) * $totalBest, 0);
                $worstVol  = (int) round(($pct / 100.0) * $totalWorst, 0);

                $budgetRows[] = [
                    'client_profit_center_id' => $cpcId,
                    'fiscal_year'             => $fy,
                    'month'                   => $m,
                    'volume'                  => $budgetVol,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ];

                if ($forecastEqualsBudget) {
                    $forecastRows[] = [
                        'client_profit_center_id' => $cpcId,
                        'fiscal_year'             => $fy,
                        'month'                   => $m,
                        'volume'                  => $budgetVol,
                        'version'                 => 1,
                        'user_id'                 => null,
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ];
                }

                // Debug log (línea verde/roja)
                $debugRows[] = [
                    'client_profit_center_id' => $cpcId,
                    'fiscal_year'             => $nextFY,
                    'budget_year'             => $fy,
                    'budget_month'            => $m,
                    'month_name'              => $slot['name'],
                    'sales_volume'            => 0.0,
                    'best_case'               => $bestPct,
                    'worst_case'              => $worstPct,
                    'seasonality_base'        => $this->lastYtdPct,
                    'forecast_base'           => $base,
                    'total_budget'            => $totalBest,
                    'monthly_pct'             => $pct,
                    'monthly_volume'          => $budgetVol,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ];
            }

            // Upsert budgets (índice único) :contentReference[oaicite:4]{index=4}
            DB::table('budgets')->upsert(
                $budgetRows,
                ['client_profit_center_id','fiscal_year','month'],
                ['volume','updated_at']
            );
            $budgetsCnt += count($budgetRows);

            // Upsert forecasts cuando corresponda
            if (!empty($forecastRows)) {
                DB::table('forecasts')->upsert(
                    $forecastRows,
                    ['client_profit_center_id','fiscal_year','month','version'],
                    ['volume','updated_at']
                );
                $forecastsCnt += count($forecastRows);
            }

            // Insert debug
            DB::table('budget_debug_log')->insert($debugRows);
        }

        return response()->json([
            'message' => "✅ Budget FY {$nextFY} generado.",
            'budgets_rows' => $budgetsCnt,
            'forecasts_rows' => $forecastsCnt,
        ]);
    }

    /** Estado intermedio para logging */
    private float $lastYtdPct = 0.0;

    /** FY siguiente (abril–marzo) */
    private function nextFiscalYear(Carbon $date): int
    {
        $fy = $date->year;
        if ($date->month < self::FISCAL_START_MONTH) $fy -= 1;
        return $fy + 1;
    }

    /**
     * Ventas YTD anualizadas con seasonality: Jan–Mar del FY-1 y Apr..mes_cap del FY actual (VBA).
     */
    private function calculateBaseForecast(int $cpcId, array $mapCurr, array $mapPrev): float
    {
        $now = Carbon::now();
        $cap = max(1, $now->month - 1);
        $year = (int)$now->year;

        $salesYTD = (float) DB::table('sales')
            ->where('client_profit_center_id', $cpcId)
            ->where('fiscal_year', $year)
            ->whereBetween('month', [1, $cap])
            ->sum('volume');

        if ($salesYTD <= 0) { $this->lastYtdPct = 0.0; return 0.0; }

        $names = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
        $ytdPct = 0.0;
        for ($m=1; $m <= $cap; $m++) {
            $label = $names[$m];
            $ytdPct += (float)( $m <= 3 ? ($mapPrev[$label] ?? 0.0) : ($mapCurr[$label] ?? 0.0) );
        }
        $this->lastYtdPct = (float) number_format($ytdPct, 2, '.', '');
        if ($ytdPct <= 0) return 0.0;

        return $salesYTD / ($ytdPct / 100.0);
    }

    /**
     * Mapea fila de seasonality -> array ['Jan'=>float,...].
     */
    private function mapSeasonRow(object $row): array
    {
        $out = [];
        $map = [
            'Jan'=>'jan','Feb'=>'feb','Mar'=>'mar','Apr'=>'apr','May'=>'may','Jun'=>'jun',
            'Jul'=>'jul','Aug'=>'aug','Sep'=>'sep','Oct'=>'oct','Nov'=>'nov','Dec'=>'dec',
        ];
        foreach ($map as $cap => $low) {
            $v = isset($row->{$low}) ? $row->{$low} : (isset($row->{$cap}) ? $row->{$cap} : 0.0);
            if (is_string($v)) $v = str_replace(',', '.', $v);
            $out[$cap] = (float) $v;
        }
        return $out;
    }

    /**
     * Resuelve porcentajes y si forecast=budget según clasificación y modo PA/PB.
     * A/B: toma de budget_cases (por CPC+FY) :contentReference[oaicite:5]{index=5}.
     * C/D: desde request.
     * X: ignorado (no llega acá).
     * PA/PB: D | AB_BUDGET_CASES | AB_MANUAL.
     */
    private function resolvePcts(
        int $classId,
        int $cpcId,
        int $nextFY,
        float $bestC, float $worstC,
        float $bestD, float $worstD,
        string $paPbMode,
        array $paPbCases
    ): array {
        // Por defecto, forecast = budget sólo en C/D/PA/PB (según modo). En A/B no.
        $forecastEqualsBudget = in_array($classId, [3,4,6,7], true);

        if ($classId === 1 || $classId === 2) {
            $case = DB::table('budget_cases')
                ->where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $nextFY)
                ->first(); // índice único en tabla :contentReference[oaicite:6]{index=6}
            $best = (float)($case->best_case ?? 0);
            $worst= (float)($case->worst_case ?? 0);
            $forecastEqualsBudget = false;
            return [$best, $worst, $forecastEqualsBudget];
        }

        if ($classId === 3) { // C
            return [$bestC, $worstC, true];
        }
        if ($classId === 4) { // D
            return [$bestD, $worstD, true];
        }
        if ($classId === 6 || $classId === 7) { // PA / PB
            if ($paPbMode === 'D') {
                return [$bestD, $worstD, true];
            }
            if ($paPbMode === 'AB_BUDGET_CASES') {
                $case = DB::table('budget_cases')
                    ->where('client_profit_center_id', $cpcId)
                    ->where('fiscal_year', $nextFY)
                    ->first();
                $best = (float)($case->best_case ?? 0);
                $worst= (float)($case->worst_case ?? 0);
                return [$best, $worst, true]; // si querés que NO copie a forecast, poné false
            }
            // AB_MANUAL: buscar en el payload por CPC
            $entry = $paPbCases[$cpcId] ?? null;
            $best = (float)($entry['best'] ?? 0);
            $worst= (float)($entry['worst'] ?? 0);
            return [$best, $worst, true];
        }

        // fallback
        return [0.0, 0.0, false];
    }
}
