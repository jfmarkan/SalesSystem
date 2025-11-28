<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BudgetCaseSimulatorController extends Controller
{
    private const FISCAL_START_MONTH = 4; // Apr

    // PCs que se tratan como m³ (no VK-EH)
    private const SPECIAL_M3_PC_CODES = [110, 170, 171, 175];

    public function simulate(Request $request)
    {
        // Expect exactly client_group_number + profit_center_code + percentages
        $validated = $request->validate([
            'client_group_number' => ['required'], // string|int accepted
            'profit_center_code'  => ['required'],
            'best_case'           => ['nullable','numeric','between:-100,100'],
            'worst_case'          => ['nullable','numeric','between:-100,100'],
            'compare_current'     => ['nullable','boolean'],
        ]);

        $trace_id            = (string) Str::uuid();
        $client_group_number = (string) $validated['client_group_number'];
        $profit_center_code  = (string) $validated['profit_center_code'];
        $best_pct            = (float) ($validated['best_case']  ?? 0.0);
        $worst_pct           = (float) ($validated['worst_case'] ?? 0.0);
        $compare_current     = (bool)  ($validated['compare_current'] ?? true);

        $now           = Carbon::now();
        $cap_month     = max(1, min(12, $now->month - 1)); // último mes completo
        $calendar_year = (int) $now->year;
        $current_fy    = $this->fiscalYearFromDate($now);

        // ¿Este PC es de los "raros" que van en m³?
        $isSpecialM3Pc = in_array((int)$profit_center_code, self::SPECIAL_M3_PC_CODES, true);

        // Resolve CPC by (client_group_number, profit_center_code)
        $cpc = DB::table('client_profit_centers')
            ->where('client_group_number', $client_group_number)
            ->where('profit_center_code', $profit_center_code)
            ->first();

        if (!$cpc) {
            return response()->json([
                'message' => 'Client–Profit-Center Zuordnung nicht gefunden.',
                'input'   => compact('client_group_number','profit_center_code')
            ], 422)->header('X-BCS-Trace-Id', $trace_id);
        }

        $client_profit_center_id = (int) $cpc->id;

        // ==== BASE DE VENTAS YTD (JAN..cap del año calendario actual) ====
        // Para la mayoría: SUM(sales.sales_units)  → VK-EH
        // Para PCs especiales (110/170/171/175): SUM(sales.sales_units * factor_to_m3) → m³
        $sales_rows = $this->loadSalesYtdByMonth(
            $client_profit_center_id,
            $profit_center_code,
            $calendar_year,
            $cap_month,
            $isSpecialM3Pc
        ); // array month => valor (int/float, ya en unidades correctas)

        $total_sales_ytd = 0.0;
        for ($m = 1; $m <= $cap_month; $m++) {
            $total_sales_ytd += (float) ($sales_rows[$m] ?? 0.0);
        }

        // Seasonality rows for this PC code:
        $season_max_row = DB::table('seasonalities')
            ->where('profit_center_code', $profit_center_code)
            ->orderBy('fiscal_year','desc')
            ->first();

        if (!$season_max_row) {
            return response()->json([
                'message' => 'Saisonalitätsdaten fehlen.',
                'input'   => compact('client_group_number','profit_center_code')
            ], 422)->header('X-BCS-Trace-Id', $trace_id);
        }

        $season_prev_row = DB::table('seasonalities')
            ->where('profit_center_code', $profit_center_code)
            ->where('fiscal_year', '<', $season_max_row->fiscal_year)
            ->orderBy('fiscal_year','desc')
            ->first();

        $season_max  = $this->mapSeasonRow($season_max_row);
        $season_prev = $this->mapSeasonRow($season_prev_row ?? $season_max_row);

        // Seasonality YTD sum: Jan..Mar from prev FY, Apr..cap from latest FY
        $names   = [
            1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
            7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'
        ];
        $ytd_pct = 0.0;
        for ($m = 1; $m <= $cap_month; $m++) {
            $nm  = $names[$m];
            $pct = ($m <= 3) ? ($season_prev[$nm] ?? 0.0) : ($season_max[$nm] ?? 0.0);
            $ytd_pct += (float) $pct;
        }

        if ($ytd_pct <= 0.0 || $total_sales_ytd <= 0.0) {
            return response()->json([
                'message' => 'Keine ausreichende Basis für die Simulation.',
                'basis'   => [
                    'calendarYear'  => $calendar_year,
                    'capMonth'      => $cap_month,
                    'totalSalesYTD' => (float) $total_sales_ytd,
                    'ytdPct'        => (float) number_format($ytd_pct, 2, '.', ''),
                    'baseForecast'  => 0.0,
                    'baseUnit'      => $isSpecialM3Pc ? 'm3' : 'units',
                ],
                'seriesTarget'  => [],
                'seriesCurrent' => [],
                'debug'         => [
                    'client_profit_center_id' => $client_profit_center_id,
                    'profit_center_code'      => $profit_center_code,
                ]
            ])->header('X-BCS-Trace-Id', $trace_id);
        }

        // Base forecast = convert YTD to 100%
        $base_forecast = $total_sales_ytd / ($ytd_pct / 100.0);

        // Apply best/worst percentages to annual base
        $total_best  = $base_forecast * (1 + $best_pct  / 100.0);
        $total_worst = $base_forecast * (1 + $worst_pct / 100.0);

        // Distribute Apr..Mar using latest seasonality
        $fiscal_order = [
            ['name'=>'Apr','m'=>4],['name'=>'May','m'=>5],['name'=>'Jun','m'=>6],
            ['name'=>'Jul','m'=>7],['name'=>'Aug','m'=>8],['name'=>'Sep','m'=>9],
            ['name'=>'Oct','m'=>10],['name'=>'Nov','m'=>11],['name'=>'Dec','m'=>12],
            ['name'=>'Jan','m'=>1], ['name'=>'Feb','m'=>2], ['name'=>'Mar','m'=>3],
        ];
        $series_target = [];
        foreach ($fiscal_order as $slot) {
            $pct  = (float) ($season_max[$slot['name']] ?? 0.0);
            $base = (float) (($pct / 100.0) * $base_forecast);
            $best = (float) (($pct / 100.0) * $total_best);
            $worst= (float) (($pct / 100.0) * $total_worst);
            $series_target[] = [
                'month'  => $slot['m'],
                'label'  => $slot['name'],
                'pct'    => (float) number_format($pct, 2, '.', ''),
                'base'   => (int) round($base, 0),
                'best'   => (int) round($best, 0),
                'worst'  => (int) round($worst, 0),
            ];
        }

        // Optional: current FY actuals by FY (Apr..Mar)
        $series_current = $compare_current
            ? $this->currentFYActualsSeries($client_profit_center_id, $current_fy, $isSpecialM3Pc)
            : [];

        return response()->json([
            'message' => 'Simulation berechnet.',
            'basis'   => [
                'calendarYear'  => $calendar_year,
                'capMonth'      => $cap_month,
                'totalSalesYTD' => (float) number_format($total_sales_ytd, 2, '.', ''),
                'ytdPct'        => (float) number_format($ytd_pct, 2, '.', ''),
                'baseForecast'  => (float) number_format($base_forecast, 2, '.', ''),
                'totalBest'     => (float) number_format($total_best, 2, '.', ''),
                'totalWorst'    => (float) number_format($total_worst, 2, '.', ''),
                'baseUnit'      => $isSpecialM3Pc ? 'm3' : 'units', // <- clave para el front
            ],
            'seriesTarget'  => $series_target,
            'seriesCurrent' => $series_current,
            'debug'         => [
                'client_profit_center_id' => $client_profit_center_id,
                'profit_center_code'      => $profit_center_code,
                'special_m3_pc'           => $isSpecialM3Pc,
            ]
        ])->header('X-BCS-Trace-Id', $trace_id);
    }

    private function fiscalYearFromDate(Carbon $date): int
    {
        $fy = $date->year;
        if ($date->month < self::FISCAL_START_MONTH) $fy -= 1;
        return $fy;
    }

    // Normaliza seasonality row a ['Jan'=>float,...,'Dec'=>float] (acepta coma)
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
     * Carga ventas YTD JAN..$capMonth por mes:
     * - PCs normales → SUM(sales_units)
     * - PCs especiales → SUM(sales_units * factor_to_m3)  (m³)
     *
     * Devuelve array [ month(int) => float ]
     */
    private function loadSalesYtdByMonth(
        int $cpcId,
        string $profit_center_code,
        int $calendarYear,
        int $capMonth,
        bool $asM3
    ): array {
        if ($capMonth < 1) return [];

        if ($asM3) {
            // m³: sales_units * factor_to_m3
            $ucAgg = DB::table('unit_conversions')
                ->select(
                    'profit_center_code',
                    DB::raw('MAX(factor_to_m3) as factor_to_m3')
                )
                ->groupBy('profit_center_code');

            $rows = DB::table('sales')
                ->join('client_profit_centers', 'client_profit_centers.id', '=', 'sales.client_profit_center_id')
                ->leftJoinSub($ucAgg, 'uc', function($j) {
                    $j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code');
                })
                ->where('sales.client_profit_center_id', $cpcId)
                ->where('sales.fiscal_year', $calendarYear)
                ->whereBetween('sales.month', [1, $capMonth])
                ->groupBy('sales.month')
                ->select(
                    'sales.month',
                    DB::raw('SUM(sales.sales_units * COALESCE(uc.factor_to_m3,1)) AS v')
                )
                ->pluck('v','sales.month');
        } else {
            // VK-EH: sales_units “crudas”
            $rows = DB::table('sales')
                ->where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $calendarYear)
                ->whereBetween('month', [1, $capMonth])
                ->groupBy('month')
                ->select('month', DB::raw('SUM(sales_units) AS v'))
                ->pluck('v','month');
        }

        $out = [];
        foreach ($rows as $m => $v) {
            $out[(int)$m] = (float)$v;
        }
        return $out;
    }

    /**
     * Actuals del FY actual, por mes fiscal (Apr..Mar).
     * - Si $asM3 = true → valores en m³.
     * - Si $asM3 = false → valores en sales_units.
     */
    private function currentFYActualsSeries(int $cpc_id, int $current_fy, bool $asM3): array
    {
        $fiscal = [
            ['name'=>'Apr','m'=>4],['name'=>'May','m'=>5],['name'=>'Jun','m'=>6],
            ['name'=>'Jul','m'=>7],['name'=>'Aug','m'=>8],['name'=>'Sep','m'=>9],
            ['name'=>'Oct','m'=>10],['name'=>'Nov','m'=>11],['name'=>'Dec','m'=>12],
            ['name'=>'Jan','m'=>1], ['name'=>'Feb','m'=>2], ['name'=>'Mar','m'=>3],
        ];

        if ($asM3) {
            $ucAgg = DB::table('unit_conversions')
                ->select(
                    'profit_center_code',
                    DB::raw('MAX(factor_to_m3) as factor_to_m3')
                )
                ->groupBy('profit_center_code');

            $rows = DB::table('sales')
                ->join('client_profit_centers', 'client_profit_centers.id', '=', 'sales.client_profit_center_id')
                ->leftJoinSub($ucAgg, 'uc', function($j) {
                    $j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code');
                })
                ->where('sales.client_profit_center_id', $cpc_id)
                ->where('sales.fiscal_year', $current_fy)
                ->groupBy('sales.month')
                ->select(
                    'sales.month',
                    DB::raw('SUM(sales.sales_units * COALESCE(uc.factor_to_m3,1)) AS v')
                )
                ->pluck('v','sales.month');
        } else {
            $rows = DB::table('sales')
                ->where('client_profit_center_id', $cpc_id)
                ->where('fiscal_year', $current_fy)
                ->groupBy('month')
                ->select('month', DB::raw('SUM(sales_units) AS v'))
                ->pluck('v','month');
        }

        $map = [];
        foreach ($rows as $m => $v) {
            $map[(int)$m] = (float)$v;
        }

        $series = [];
        foreach ($fiscal as $slot) {
            $m   = (int)$slot['m'];
            $val = $map[$m] ?? 0.0;
            $series[] = [
                'fiscal_year' => $current_fy,
                'month'       => $m,
                'label'       => $slot['name'],
                'actual'      => (int) round($val, 0),
            ];
        }
        return $series;
    }
}
