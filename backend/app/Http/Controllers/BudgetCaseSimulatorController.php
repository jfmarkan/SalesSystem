<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BudgetCaseSimulatorController extends Controller
{
    private const FISCAL_START_MONTH = 4; // Apr

    public function simulate(Request $request)
    {
        // Only CPC + percentages. NO fiscal_year lookup. NO budget_cases lookup.
        $validated = $request->validate([
            'client_profit_center_id' => ['required','integer','exists:client_profit_centers,id'],
            'best_case'               => ['nullable','numeric','between:-100,100'],
            'worst_case'              => ['nullable','numeric','between:-100,100'],
            'compare_current'         => ['nullable','boolean'],
        ]);

        $traceId     = (string) Str::uuid();
        $cpcId       = (int) $validated['client_profit_center_id'];
        $bestPct     = (float) ($validated['best_case']  ?? 0.0);
        $worstPct    = (float) ($validated['worst_case'] ?? 0.0);
        $compareCurr = (bool)  ($validated['compare_current'] ?? true);

        $now          = Carbon::now();
        $capMonth     = max(1, min(12, $now->month - 1)); // last complete month (Aug -> 7 = Jul)
        $calendarYear = (int) $now->year;
        $currentFY    = $this->fiscalYearFromDate($now);

        // Resolve PC code from CPC
        $pcCode = DB::table('client_profit_centers')->where('id', $cpcId)->value('profit_center_code');
        if (!$pcCode) {
            return response()->json(['message' => 'Profitcenter nicht gefunden.'], 422)
                ->header('X-BCS-Trace-Id', $traceId);
        }

        // Seasonality rows for this PC:
        // - seasonMax: latest FY -> used for Apr..cap YTD and for distributing Apr..Mar
        // - seasonPrev: previous FY (fallback to seasonMax) -> used for Jan..Mar YTD
        $seasonMaxRow = DB::table('seasonalities')
            ->where('profit_center_code', $pcCode)
            ->orderBy('fiscal_year','desc')
            ->first();

        if (!$seasonMaxRow) {
            return response()->json(['message' => 'Saisonalitätsdaten fehlen.'], 422)
                ->header('X-BCS-Trace-Id', $traceId);
        }

        $seasonPrevRow = DB::table('seasonalities')
            ->where('profit_center_code', $pcCode)
            ->where('fiscal_year', '<', $seasonMaxRow->fiscal_year)
            ->orderBy('fiscal_year', 'desc')
            ->first();

        $seasonMax  = $this->mapSeasonRow($seasonMaxRow);
        $seasonPrev = $this->mapSeasonRow($seasonPrevRow ?? $seasonMaxRow);

        // SALES YTD (calendar Jan..cap of current calendar year)
        $salesRows = DB::table('sales')
            ->select('month', DB::raw('SUM(volume) AS v'))
            ->where('client_profit_center_id', $cpcId)
            ->where('fiscal_year', $calendarYear) // calendar year column
            ->whereBetween('month', [1, $capMonth])
            ->groupBy('month')
            ->pluck('v','month');

        $totalSalesYTD = 0;
        for ($m = 1; $m <= $capMonth; $m++) {
            $totalSalesYTD += (int) round((float)($salesRows[$m] ?? 0), 0);
        }

        // Seasonality YTD sum: Jan..Mar from prev FY, Apr..cap from max FY
        $names  = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
        $ytdPct = 0.0;
        for ($m = 1; $m <= $capMonth; $m++) {
            $name = $names[$m];
            $pct  = ($m <= 3) ? ($seasonPrev[$name] ?? 0.0) : ($seasonMax[$name] ?? 0.0);
            $ytdPct += (float) $pct;
        }

        if ($ytdPct <= 0.0 || $totalSalesYTD <= 0.0) {
            return response()->json([
                'message' => 'Keine ausreichende Basis für die Simulation.',
                'basis'   => [
                    'totalSalesYTD' => (int) $totalSalesYTD,
                    'ytdPct'        => (float) number_format($ytdPct, 2, '.', ''),
                    'baseForecast'  => 0.0,
                ],
                'seriesTarget'  => [],
                'seriesCurrent' => [],
            ])->header('X-BCS-Trace-Id', $traceId);
        }

        // Base forecast = convert YTD to 100%
        $baseForecast = $totalSalesYTD / ($ytdPct / 100.0);

        // Apply best/worst percentages to annual base
        $totalBest  = $baseForecast * (1 + $bestPct  / 100.0);
        $totalWorst = $baseForecast * (1 + $worstPct / 100.0);

        // Distribute Apr..Mar using latest seasonality (seasonMax)
        $fiscalOrder = [
            ['name'=>'Apr','m'=>4],['name'=>'May','m'=>5],['name'=>'Jun','m'=>6],
            ['name'=>'Jul','m'=>7],['name'=>'Aug','m'=>8],['name'=>'Sep','m'=>9],
            ['name'=>'Oct','m'=>10],['name'=>'Nov','m'=>11],['name'=>'Dec','m'=>12],
            ['name'=>'Jan','m'=>1],['name'=>'Feb','m'=>2],['name'=>'Mar','m'=>3],
        ];

        $seriesTarget = [];
        foreach ($fiscalOrder as $slot) {
            $pct = (float) ($seasonMax[$slot['name']] ?? 0.0);
            $baseVol  = (int) round(($pct / 100.0) * $baseForecast, 0);
            $bestVol  = (int) round(($pct / 100.0) * $totalBest, 0);
            $worstVol = (int) round(($pct / 100.0) * $totalWorst, 0);

            $seriesTarget[] = [
                'month'  => $slot['m'],
                'label'  => $slot['name'],
                'pct'    => (float) number_format($pct, 2, '.', ''),
                'base'   => $baseVol,
                'best'   => $bestVol,
                'worst'  => $worstVol,
            ];
        }

        $seriesCurrent = $compareCurr ? $this->currentFYActualsSeries($cpcId, $currentFY) : [];

        return response()->json([
            'message' => 'Simulation berechnet.',
            'basis'   => [
                'totalSalesYTD' => (int) $totalSalesYTD,
                'ytdPct'        => (float) number_format($ytdPct, 2, '.', ''),
                'baseForecast'  => (float) number_format($baseForecast, 2, '.', ''),
                'totalBest'     => (float) number_format($totalBest, 2, '.', ''),
                'totalWorst'    => (float) number_format($totalWorst, 2, '.', ''),
            ],
            'seriesTarget'  => $seriesTarget,   // Apr..Mar monthly best/worst/base
            'seriesCurrent' => $seriesCurrent,  // Apr..Mar actuals of current FY (optional)
        ])->header('X-BCS-Trace-Id', $traceId);
    }

    private function fiscalYearFromDate(Carbon $date): int
    {
        $fy = $date->year;
        if ($date->month < self::FISCAL_START_MONTH) $fy -= 1;
        return $fy;
    }

    /** Map row to ['Jan'=>float,...,'Dec'=>float] accepting either lower/upper cases and comma decimals. */
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

    private function currentFYActualsSeries(int $cpcId, int $currentFY): array
    {
        $fiscalOrder = [
            ['name'=>'Apr','m'=>4],['name'=>'May','m'=>5],['name'=>'Jun','m'=>6],
            ['name'=>'Jul','m'=>7],['name'=>'Aug','m'=>8],['name'=>'Sep','m'=>9],
            ['name'=>'Oct','m'=>10],['name'=>'Nov','m'=>11],['name'=>'Dec','m'=>12],
            ['name'=>'Jan','m'=>1],['name'=>'Feb','m'=>2],['name'=>'Mar','m'=>3],
        ];
        $series = [];
        foreach ($fiscalOrder as $slot) {
            $val = (int) round((float) DB::table('sales')
                ->where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $currentFY)
                ->where('month', $slot['m'])
                ->sum('volume'), 0);

            $series[] = [
                'fiscal_year' => $currentFY,
                'month'       => $slot['m'],
                'label'       => $slot['name'],
                'actual'      => $val,
            ];
        }
        return $series;
    }
}