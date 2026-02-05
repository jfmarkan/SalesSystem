<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BudgetGenerationController extends Controller
{
    private const FISCAL_START_MONTH = 4; // Abril
    private const CAP_MONTH = 10;         // Jan..Oct
    private const M3_NATIVE_PCS = [110, 170, 171, 175];

    /** Estado intermedio para logging */
    private float $lastYtdPct = 0.0;

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'best_case_c'   => ['required','numeric','between:-100,100'],
            'worst_case_c'  => ['required','numeric','between:-100,100'],
            'best_case_d'   => ['required','numeric','between:-100,100'],
            'worst_case_d'  => ['required','numeric','between:-100,100'],
            'fiscal_year'   => ['nullable','integer','min:2024'],
        ]);

        $bestC  = (float)$validated['best_case_c'];
        $worstC = (float)$validated['worst_case_c'];
        $bestD  = (float)$validated['best_case_d'];
        $worstD = (float)$validated['worst_case_d'];

        $now = Carbon::now();
        $nextFY = isset($validated['fiscal_year'])
            ? (int)$validated['fiscal_year']
            : $this->nextFiscalYear($now);

        $baseCalendarYear = $nextFY - 1;

        $months = [
            ['name'=>'Apr','m'=>4],['name'=>'May','m'=>5],['name'=>'Jun','m'=>6],
            ['name'=>'Jul','m'=>7],['name'=>'Aug','m'=>8],['name'=>'Sep','m'=>9],
            ['name'=>'Oct','m'=>10],['name'=>'Nov','m'=>11],['name'=>'Dec','m'=>12],
            ['name'=>'Jan','m'=>1], ['name'=>'Feb','m'=>2], ['name'=>'Mar','m'=>3],
        ];

        $cpcIds = DB::table('sales')
            ->select('client_profit_center_id')
            ->distinct()
            ->pluck('client_profit_center_id');

        $budgetsCnt   = 0;
        $forecastsCnt = 0;

        foreach ($cpcIds as $cpcId) {
            $cpc = DB::table('client_profit_centers')->where('id', $cpcId)->first();
            if (!$cpc) continue;

            $client = DB::table('clients')->where('client_group_number', $cpc->client_group_number)->first();
            if (!$client) continue;

            $classId = (int)$client->classification_id; // 1=A,2=B,3=C,4=D,5=X,6=PA,7=PB
            if ($classId === 5) continue; // X

            $pcCode = (string)$cpc->profit_center_code;

            // seasonality del año generado con fallback
            $seasonMap = $this->loadSeasonalityMapWithFallback($pcCode, $nextFY);
            if (!$seasonMap) continue;

            // base anual en m³ desde Jan..Oct del año base calendario
            $baseM3 = $this->calculateBaseAnnualM3(
                (int)$cpcId,
                $pcCode,
                $nextFY,
                $baseCalendarYear,
                self::CAP_MONTH,
                $seasonMap
            );
            if ($baseM3 <= 0.0) continue;

            // best/worst + si forecast = budget
            [$bestPct, $worstPct, $forecastEqualsBudget] = $this->resolvePcts(
                $classId,
                (int)$cpcId,
                (int)$nextFY,
                $bestC, $worstC, $bestD, $worstD
            );

            $totalBestM3  = $baseM3 * (1 + $bestPct  / 100.0);
            $totalWorstM3 = $baseM3 * (1 + $worstPct / 100.0);

            $isM3Native = $this->isM3NativePc($pcCode);
            $factorToM3 = $isM3Native ? 1.0 : $this->factorToM3($pcCode, $nextFY);

            // ✅ Si es C/D y hay forecast automático, resolver owner user_id desde assignments
            $forecastUserId = null;
            if ($forecastEqualsBudget) {
                $forecastUserId = $this->resolveAssignedUserId((int)$cpcId);
                // Si no hay assignment, queda null; el forecast existirá pero no lo verá nadie.
                // Podés decidir si en ese caso preferís skippear forecast.
            }

            $budgetRows   = [];
            $forecastRows = [];
            $debugRows    = [];

            foreach ($months as $slot) {
                $m  = $slot['m'];
                $fy = ($m >= self::FISCAL_START_MONTH) ? $nextFY : ($nextFY + 1);

                $pct = (float)($seasonMap[$slot['name']] ?? 0.0);

                $monthM3Best  = ($pct / 100.0) * $totalBestM3;
                $monthM3Worst = ($pct / 100.0) * $totalWorstM3;

                // guardar en budgets.volume:
                // - PCs m³ nativos => m³
                // - PCs normales => UNITS (para que overview convierta con factor_to_m3)
                $budgetVol = $isM3Native
                    ? (int) round($monthM3Best, 0)
                    : (int) round(($factorToM3 > 0 ? ($monthM3Best / $factorToM3) : 0.0), 0);

                $budgetRows[] = [
                    'client_profit_center_id' => $cpcId,
                    'fiscal_year'             => $fy,
                    'month'                   => $m,
                    'volume'                  => $budgetVol,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ];

                // ✅ C/D => forecast = budget y con user_id del assignment
                if ($forecastEqualsBudget) {
                    $forecastRows[] = [
                        'client_profit_center_id' => $cpcId,
                        'fiscal_year'             => $fy,
                        'month'                   => $m,
                        'volume'                  => $budgetVol,
                        'version'                 => 1,
                        'user_id'                 => $forecastUserId, // ✅ clave
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ];
                }

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
                    'forecast_base'           => $baseM3,
                    'total_budget'            => $totalBestM3,
                    'monthly_pct'             => $pct,
                    'monthly_volume'          => $budgetVol,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ];
            }

            DB::table('budgets')->upsert(
                $budgetRows,
                ['client_profit_center_id','fiscal_year','month'],
                ['volume','updated_at']
            );
            $budgetsCnt += count($budgetRows);

            if (!empty($forecastRows)) {
                DB::table('forecasts')->upsert(
                    $forecastRows,
                    ['client_profit_center_id','fiscal_year','month','version'],
                    // ✅ importante: actualizar user_id también
                    ['volume','user_id','updated_at']
                );
                $forecastsCnt += count($forecastRows);
            }

            DB::table('budget_debug_log')->insert($debugRows);
        }

        return response()->json([
            'message'           => "✅ Budget FY {$nextFY} generado.",
            'target_fiscal_year'=> $nextFY,
            'base_calendar_year'=> $baseCalendarYear,
            'cap_month'         => self::CAP_MONTH,
            'budgets_rows'      => $budgetsCnt,
            'forecasts_rows'    => $forecastsCnt,
            'note'              => 'Forecast C/D: user_id se asigna desde assignments (si existe). Jan-Mar quedan con fiscal_year = targetFY+1.',
        ]);
    }

    private function nextFiscalYear(Carbon $date): int
    {
        $fy = $date->year;
        if ($date->month < self::FISCAL_START_MONTH) $fy -= 1;
        return $fy + 1;
    }

    private function resolveAssignedUserId(int $cpcId): ?int
    {
        // Tomamos el assignment más reciente con user_id no null
        $userId = DB::table('assignments')
            ->where('client_profit_center_id', $cpcId)
            ->whereNotNull('user_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->value('user_id');

        return $userId ? (int)$userId : null;
    }

    private function isM3NativePc(string $pcCode): bool
    {
        return in_array((int)$pcCode, self::M3_NATIVE_PCS, true);
    }

    private function factorToM3(string $pcCode, int $targetFy): float
    {
        $row = DB::table('unit_conversions')
            ->where('profit_center_code', $pcCode)
            ->where('fiscal_year', $targetFy)
            ->first();

        $f = $row ? (float)($row->factor_to_m3 ?? 1) : 1.0;
        return $f > 0 ? $f : 1.0;
    }

    private function calculateBaseAnnualM3(
        int $cpcId,
        string $pcCode,
        int $targetFy,
        int $baseCalendarYear,
        int $capMonth,
        array $seasonMap
    ): float {
        $isM3Native = $this->isM3NativePc($pcCode);
        $factorToM3 = $isM3Native ? 1.0 : $this->factorToM3($pcCode, $targetFy);

        if ($isM3Native) {
            $ytdM3 = (float) DB::table('sales')
                ->where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $baseCalendarYear)
                ->whereBetween('month', [1, $capMonth])
                ->sum('cubic_meters');
        } else {
            $units = (float) DB::table('sales')
                ->where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $baseCalendarYear)
                ->whereBetween('month', [1, $capMonth])
                ->sum('sales_units');

            $ytdM3 = $units * $factorToM3;
        }

        if ($ytdM3 <= 0.0) {
            $this->lastYtdPct = 0.0;
            return 0.0;
        }

        $ytdPct = $this->seasonalityPctForMonths($seasonMap, 1, $capMonth);
        $this->lastYtdPct = (float) number_format($ytdPct, 2, '.', '');

        if ($ytdPct <= 0.0) return 0.0;

        return $ytdM3 / ($ytdPct / 100.0);
    }

    private function loadSeasonalityMapWithFallback(string $pcCode, int $desiredYear): ?array
    {
        $row = DB::table('seasonalities')
            ->where('profit_center_code', $pcCode)
            ->where('fiscal_year', $desiredYear)
            ->first();

        if (!$row) {
            $row = DB::table('seasonalities')
                ->where('profit_center_code', $pcCode)
                ->where('fiscal_year', '<', $desiredYear)
                ->orderByDesc('fiscal_year')
                ->first();
        }

        if (!$row) {
            $row = DB::table('seasonalities')
                ->where('profit_center_code', $pcCode)
                ->orderByDesc('fiscal_year')
                ->first();
        }

        return $row ? $this->mapSeasonRow($row) : null;
    }

    private function seasonalityPctForMonths(array $seasonMap, int $fromMonth, int $toMonth): float
    {
        $names = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
        $pct = 0.0;
        for ($m = $fromMonth; $m <= $toMonth; $m++) {
            $pct += (float)($seasonMap[$names[$m]] ?? 0.0);
        }
        return $pct;
    }

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
     * A/B/PA/PB:
     * - si no hay budget_case o skip_budget => presupuesto 0 (consistente con overview)
     * C/D:
     * - usa request y forecast = budget
     */
    private function resolvePcts(
        int $classId,
        int $cpcId,
        int $nextFY,
        float $bestC, float $worstC,
        float $bestD, float $worstD
    ): array {
        if (in_array($classId, [1, 2, 6, 7], true)) {
            $case = DB::table('budget_cases')
                ->where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $nextFY)
                ->first();

            if (!$case) return [-100.0, -100.0, false];

            $skip = (bool)($case->skip_budget ?? false);
            if ($skip) return [-100.0, -100.0, false];

            $best  = (float)($case->best_case ?? 0.0);
            $worst = (float)($case->worst_case ?? 0.0);
            return [$best, $worst, false];
        }

        if ($classId === 3) return [$bestC, $worstC, true];
        if ($classId === 4) return [$bestD, $worstD, true];

        return [0.0, 0.0, false];
    }
}
