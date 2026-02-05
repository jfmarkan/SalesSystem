<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BudgetCaseSimulatorController extends Controller
{
    private const FISCAL_START_MONTH = 4; // Apr
    private const CAP_MONTH = 10;         // Jan..Oct (fijo)

    // PCs que se tratan como m³ (no sales_units)
    private const SPECIAL_M3_PC_CODES = [110, 170, 171, 175];

    public function simulate(Request $request)
    {
        $validated = $request->validate([
            'client_group_number' => ['required'], // string|int accepted
            'profit_center_code'  => ['required'],
            'best_case'           => ['nullable','numeric'],
            'worst_case'          => ['nullable','numeric'],
            'compare_current'     => ['nullable','boolean'],

            // ✅ importante para alinear con generator/overview
            'target_fiscal_year'  => ['nullable','integer','min:2000'],
        ]);

        $trace_id            = (string) Str::uuid();
        $client_group_number = (string) $validated['client_group_number'];
        $profit_center_code  = (string) $validated['profit_center_code'];
        $best_pct            = (float) ($validated['best_case']  ?? 0.0);
        $worst_pct           = (float) ($validated['worst_case'] ?? 0.0);
        $compare_current     = (bool)  ($validated['compare_current'] ?? true);

        $now = Carbon::now();

        // ✅ target FY (FY start year) para simular (Abr..Mar)
        $target_fy = isset($validated['target_fiscal_year'])
            ? (int)$validated['target_fiscal_year']
            : $this->nextFiscalYear($now);

        // ✅ base calendario: Jan..Oct del año anterior al FY objetivo
        $base_calendar_year = $target_fy - 1;
        $cap_month          = self::CAP_MONTH;

        // FY actual (start year) para comparación
        $current_fy = $this->fiscalYearFromDate($now);

        $isSpecialM3Pc = in_array((int)$profit_center_code, self::SPECIAL_M3_PC_CODES, true);
        $baseUnit      = $isSpecialM3Pc ? 'm3' : 'units';

        // Resolve CPC
        $cpc = DB::table('client_profit_centers')
            ->where('client_group_number', $client_group_number)
            ->where('profit_center_code', $profit_center_code)
            ->first();

        if (!$cpc) {
            return response()->json([
                'message' => 'Client–Profit-Center Zuordnung nicht gefunden.',
                'input'   => compact('client_group_number','profit_center_code','target_fy'),
            ], 422)->header('X-BCS-Trace-Id', $trace_id);
        }

        $client_profit_center_id = (int) $cpc->id;

        // ✅ Seasonality: año objetivo con fallback inmediato inferior
        [$seasonMap, $seasonYearUsed] = $this->loadSeasonalityMapWithFallbackStrict($profit_center_code, $target_fy);
        if (!$seasonMap) {
            return response()->json([
                'message' => 'Saisonalitätsdaten fehlen.',
                'input'   => compact('client_group_number','profit_center_code','target_fy'),
            ], 422)->header('X-BCS-Trace-Id', $trace_id);
        }

        // === BASE YTD (Jan..Oct del base_calendar_year) ===
        // PCs normales → SUM(sales_units)
        // PCs especiales → SUM(cubic_meters)
        $sales_rows = $this->loadSalesYtdByMonth(
            $client_profit_center_id,
            $base_calendar_year,
            $cap_month,
            $isSpecialM3Pc
        );

        $total_sales_ytd = 0.0;
        for ($m = 1; $m <= $cap_month; $m++) {
            $total_sales_ytd += (float) ($sales_rows[$m] ?? 0.0);
        }

        // ✅ ytd_pct = suma Jan..Oct de la MISMA seasonality (sin mezclar prev/max)
        $ytd_pct = $this->seasonalityPctForMonths($seasonMap, 1, $cap_month);

        if ($ytd_pct <= 0.0 || $total_sales_ytd <= 0.0) {
            return response()->json([
                'message' => 'Keine ausreichende Basis für die Simulation.',
                'basis'   => [
                    'targetFiscalYear'   => $target_fy,
                    'baseCalendarYear'   => $base_calendar_year,
                    'capMonth'           => $cap_month,
                    'totalSalesYTD'      => (float) $total_sales_ytd,
                    'ytdPct'             => (float) number_format($ytd_pct, 2, '.', ''),
                    'baseForecast'       => 0.0,
                    'baseUnit'           => $baseUnit,
                    'seasonalityYearUsed'=> $seasonYearUsed,
                ],
                'seriesTarget'  => [],
                'seriesCurrent' => [],
                'debug'         => [
                    'trace_id'                => $trace_id,
                    'client_profit_center_id' => $client_profit_center_id,
                    'profit_center_code'      => $profit_center_code,
                    'special_m3_pc'           => $isSpecialM3Pc,
                ],
            ])->header('X-BCS-Trace-Id', $trace_id);
        }

        // ✅ Annualización (misma unidad que ventas base)
        $base_forecast = $total_sales_ytd / ($ytd_pct / 100.0);

        $total_best  = $base_forecast * (1 + $best_pct  / 100.0);
        $total_worst = $base_forecast * (1 + $worst_pct / 100.0);

        // Distribución fiscal Apr..Mar (con la MISMA seasonality)
        $fiscal_order = [
            ['name'=>'Apr','m'=>4],['name'=>'May','m'=>5],['name'=>'Jun','m'=>6],
            ['name'=>'Jul','m'=>7],['name'=>'Aug','m'=>8],['name'=>'Sep','m'=>9],
            ['name'=>'Oct','m'=>10],['name'=>'Nov','m'=>11],['name'=>'Dec','m'=>12],
            ['name'=>'Jan','m'=>1], ['name'=>'Feb','m'=>2], ['name'=>'Mar','m'=>3],
        ];

        $series_target = [];
        foreach ($fiscal_order as $slot) {
            $pct  = (float) ($seasonMap[$slot['name']] ?? 0.0);

            $base  = ($pct / 100.0) * $base_forecast;
            $best  = ($pct / 100.0) * $total_best;
            $worst = ($pct / 100.0) * $total_worst;

            $series_target[] = [
                'month'  => (int)$slot['m'],
                'label'  => (string)$slot['name'],
                'pct'    => (float) number_format($pct, 2, '.', ''),
                'base'   => (int) round($base, 0),
                'best'   => (int) round($best, 0),
                'worst'  => (int) round($worst, 0),
            ];
        }

        // Serie fiscal actual (Apr..Mar) usando sales CALENDARIO (Apr..Dec currentFY, Jan..Mar currentFY+1)
        $series_current = $compare_current
            ? $this->currentFYActualsSeriesFixed($client_profit_center_id, $current_fy, $isSpecialM3Pc)
            : [];

        return response()->json([
            'message' => 'Simulation berechnet.',
            'basis'   => [
                'targetFiscalYear'    => $target_fy,
                'baseCalendarYear'    => $base_calendar_year,
                'capMonth'            => $cap_month,
                'totalSalesYTD'       => (float) number_format($total_sales_ytd, 2, '.', ''),
                'ytdPct'              => (float) number_format($ytd_pct, 2, '.', ''),
                'baseForecast'        => (float) number_format($base_forecast, 2, '.', ''),
                'totalBest'           => (float) number_format($total_best, 2, '.', ''),
                'totalWorst'          => (float) number_format($total_worst, 2, '.', ''),
                'baseUnit'            => $baseUnit,
                'seasonalityYearUsed' => $seasonYearUsed,
            ],
            'seriesTarget'  => $series_target,
            'seriesCurrent' => $series_current,
            'debug'         => [
                'trace_id'                => $trace_id,
                'client_profit_center_id' => $client_profit_center_id,
                'profit_center_code'      => $profit_center_code,
                'special_m3_pc'           => $isSpecialM3Pc,
            ],
        ])->header('X-BCS-Trace-Id', $trace_id);
    }

    /** FY start year (Abr–Mar). Ej: Feb 2026 => FY 2025 */
    private function fiscalYearFromDate(Carbon $date): int
    {
        $fy = $date->year;
        if ($date->month < self::FISCAL_START_MONTH) $fy -= 1;
        return $fy;
    }

    /** FY siguiente (Abr–Mar). Ej: Feb 2026 => nextFY 2026 */
    private function nextFiscalYear(Carbon $date): int
    {
        $fy = $this->fiscalYearFromDate($date);
        return $fy + 1;
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
     * ✅ Seasonality con regla estricta:
     * - si existe exacta desiredYear => usarla
     * - si no => usar mayor menor a desiredYear (inmediato inferior mayor)
     * - si no hay ninguna menor => usar la más vieja disponible (caso extremo)
     *
     * @return array{0:?array,1:?int} [seasonMap, seasonYearUsed]
     */
    private function loadSeasonalityMapWithFallbackStrict(string $pcCode, int $desiredYear): array
    {
        $row = DB::table('seasonalities')
            ->where('profit_center_code', $pcCode)
            ->where('fiscal_year', $desiredYear)
            ->first();

        if ($row) {
            return [$this->mapSeasonRow($row), (int)$row->fiscal_year];
        }

        $row = DB::table('seasonalities')
            ->where('profit_center_code', $pcCode)
            ->where('fiscal_year', '<', $desiredYear)
            ->orderByDesc('fiscal_year')
            ->first();

        if ($row) {
            return [$this->mapSeasonRow($row), (int)$row->fiscal_year];
        }

        $row = DB::table('seasonalities')
            ->where('profit_center_code', $pcCode)
            ->orderBy('fiscal_year', 'asc')
            ->first();

        if ($row) {
            return [$this->mapSeasonRow($row), (int)$row->fiscal_year];
        }

        return [null, null];
    }

    private function seasonalityPctForMonths(array $seasonMap, int $fromMonth, int $toMonth): float
    {
        $names = [
            1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
            7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'
        ];
        $pct = 0.0;
        for ($m = $fromMonth; $m <= $toMonth; $m++) {
            $pct += (float)($seasonMap[$names[$m]] ?? 0.0);
        }
        return $pct;
    }

    /**
     * Ventas YTD por mes del año calendario dado:
     * - asM3=true => cubic_meters
     * - asM3=false => sales_units
     *
     * ⚠️ NO convierte unidades. Respeta tu regla.
     */
    private function loadSalesYtdByMonth(int $cpcId, int $calendarYear, int $capMonth, bool $asM3): array
    {
        if ($capMonth < 1) return [];

        $col = $asM3 ? 'cubic_meters' : 'sales_units';

        $rows = DB::table('sales')
            ->where('client_profit_center_id', $cpcId)
            ->where('fiscal_year', $calendarYear)        // en sales = año calendario
            ->whereBetween('month', [1, $capMonth])
            ->groupBy('month')
            ->select('month', DB::raw("SUM($col) AS v"))
            ->pluck('v', 'month');

        $out = [];
        foreach ($rows as $m => $v) {
            $out[(int)$m] = (float)$v;
        }
        return $out;
    }

    /**
     * ✅ Serie fiscal actual (Apr..Mar) leyendo sales por año calendario:
     * - Apr..Dec => sales.fiscal_year = currentFY
     * - Jan..Mar => sales.fiscal_year = currentFY + 1
     *
     * ⚠️ NO convierte unidades. Respeta tu regla.
     */
    private function currentFYActualsSeriesFixed(int $cpcId, int $currentFyStartYear, bool $asM3): array
    {
        $fiscal = [
            ['name'=>'Apr','m'=>4],['name'=>'May','m'=>5],['name'=>'Jun','m'=>6],
            ['name'=>'Jul','m'=>7],['name'=>'Aug','m'=>8],['name'=>'Sep','m'=>9],
            ['name'=>'Oct','m'=>10],['name'=>'Nov','m'=>11],['name'=>'Dec','m'=>12],
            ['name'=>'Jan','m'=>1], ['name'=>'Feb','m'=>2], ['name'=>'Mar','m'=>3],
        ];

        $col = $asM3 ? 'cubic_meters' : 'sales_units';

        // Traemos ambos años en 1 query
        $years = [$currentFyStartYear, $currentFyStartYear + 1];

        $rows = DB::table('sales')
            ->where('client_profit_center_id', $cpcId)
            ->whereIn('fiscal_year', $years) // sales.fiscal_year = calendario
            ->groupBy('fiscal_year', 'month')
            ->select('fiscal_year', 'month', DB::raw("SUM($col) AS v"))
            ->get();

        $map = []; // [year][month] => val
        foreach ($rows as $r) {
            $y = (int)$r->fiscal_year;
            $m = (int)$r->month;
            $map[$y][$m] = (float)$r->v;
        }

        $series = [];
        foreach ($fiscal as $slot) {
            $m = (int)$slot['m'];
            $year = ($m >= self::FISCAL_START_MONTH)
                ? $currentFyStartYear
                : ($currentFyStartYear + 1);

            $val = $map[$year][$m] ?? 0.0;

            $series[] = [
                'fiscal_year_start' => $currentFyStartYear, // FY actual start-year
                'calendar_year'     => $year,               // año calendario real del dato
                'month'             => $m,
                'label'             => (string)$slot['name'],
                'actual'            => (int) round($val, 0),
            ];
        }

        return $series;
    }
}
