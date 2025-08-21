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
        // keep payload simple: simulate for CURRENT calendar year YTD only
        $validated = $request->validate([
            'client_profit_center_id' => ['required','integer','exists:client_profit_centers,id'],
            'best_case'               => ['nullable','numeric','between:-100,100'],
            'worst_case'              => ['nullable','numeric','between:-100,100'],
            'compare_current'         => ['nullable','boolean'],
            'debug'                   => ['nullable','boolean'],
        ]);

        $traceId       = (string) Str::uuid();
        $cpcId         = (int) $validated['client_profit_center_id'];
        $now           = Carbon::now();
        $compareCurr   = (bool)($validated['compare_current'] ?? true);
        $debug         = (bool)($validated['debug'] ?? true); // default ON to see values

        // Last COMPLETE calendar month (e.g. Aug -> 7 for Jul)
        $capMonth      = max(1, min(12, $now->month - 1));
        $calendarYear  = (int) $now->year;      // YTD is ALWAYS Jan..cap of this calendar year
        $currentFY     = $this->fiscalYearFromDate($now); // just for "seriesCurrent" label

        // --- resolve Profit Center Code for THIS CPC
        $pcCode = DB::table('client_profit_centers')
            ->where('id', $cpcId)
            ->value('profit_center_code');

        if (!$pcCode) {
            return response()->json(['message' => 'Profitcenter nicht gefunden.'], 422)
                ->header('X-BCS-Trace-Id', $traceId);
        }

        // --- seasonality rows per PC:
        // maxFY = highest available FY for this PC (used for Apr..cap and distribution)
        // prevFY = highest < maxFY (used for Jan–Mar). Fallback to maxFY if not present.
        $seasonMaxRow = DB::table('seasonalities')
            ->where('profit_center_code', $pcCode)
            ->orderBy('fiscal_year', 'desc')
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

        $seasonMaxFY  = (int) $seasonMaxRow->fiscal_year;
        $seasonPrevFY = (int) ($seasonPrevRow->fiscal_year ?? ($seasonMaxFY - 1));

        $seasonMax  = $this->mapSeasonRow($seasonMaxRow);
        $seasonPrev = $this->mapSeasonRow($seasonPrevRow ?? $seasonMaxRow);

        // --- SALES YTD (Basis): sum Jan..cap of CURRENT calendar year (pure calendar)
        $salesYtdRows = DB::table('sales')
            ->select('month', DB::raw('SUM(volume) as v'))
            ->where('client_profit_center_id', $cpcId)
            ->where('fiscal_year', $calendarYear) // calendar year in your table
            ->whereBetween('month', [1, $capMonth])
            ->groupBy('month')
            ->pluck('v', 'month');

        $totalSalesYTD = 0;
        $salesYtdByMonth = [];
        for ($m = 1; $m <= $capMonth; $m++) {
            $val = (int) round((float)($salesYtdRows[$m] ?? 0), 0);
            $salesYtdByMonth[$m] = $val;
            $totalSalesYTD += $val;
        }

        // --- SAISON-ANTEIL YTD: Jan–Mar from prev season; Apr..cap from max season
        $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $ytdPct = 0.0;
        $seasonYtdByMonth = [];
        for ($m = 1; $m <= $capMonth; $m++) {
            $name = $monthNames[$m - 1];
            $pct  = ($m <= 3) ? (float)($seasonPrev[$name] ?? 0.0) : (float)($seasonMax[$name] ?? 0.0);
            $pct = (float) number_format($pct, 2, '.', ''); // normalize decimal
            $seasonYtdByMonth[$m] = $pct;
            $ytdPct += $pct;
        }

        // --- BASIS-PROGNOSE
        $baseForecast = ($ytdPct > 0) ? ($totalSalesYTD / ($ytdPct / 100.0)) : 0.0;

        // user-provided percentages only (no future FY here)
        $bestPct  = (float) ($validated['best_case']  ?? 0.0);
        $worstPct = (float) ($validated['worst_case'] ?? 0.0);

        $totalBest  = $baseForecast * (1 + $bestPct  / 100.0);
        $totalWorst = $baseForecast * (1 + $worstPct / 100.0);

        // --- MONTHLY DISTRIBUTION (Apr..Mar) using seasonMax (latest)
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
                'fiscal_year' => $currentFY + 1, // label only; simulation is calendar-based
                'month'       => $slot['m'],
                'label'       => $slot['name'],
                'pct'         => (float) number_format($pct, 2, '.', ''),
                'base'        => $baseVol,
                'best'        => $bestVol,
                'worst'       => $worstVol,
            ];
        }

        $seriesCurrent = $compareCurr ? $this->currentFYActualsSeries($cpcId, $currentFY) : [];

        // --- DEBUG payload (echoed in headers and body so you can console.log it)
        $debugPayload = [
            'trace_id'        => $traceId,
            'inputs'          => [
                'cpc_id'        => $cpcId,
                'pc_code'       => $pcCode,
                'calendar_year' => $calendarYear,
                'cap_month'     => $capMonth,
                'best_pct'      => $bestPct,
                'worst_pct'     => $worstPct,
            ],
            'seasonality'      => [
                'max_fy'        => $seasonMaxFY,
                'prev_fy'       => $seasonPrevFY,
                'ytd_by_month'  => $seasonYtdByMonth,
            ],
            'sales'           => [
                'ytd_by_month'  => $salesYtdByMonth,
                'total_ytd'     => $totalSalesYTD,
            ],
            'basis'           => [
                'ytd_pct'       => (float) number_format($ytdPct, 2, '.', ''),
                'base_forecast' => (float) number_format($baseForecast, 2, '.', ''),
                'total_best'    => (float) number_format($totalBest, 2, '.', ''),
                'total_worst'   => (float) number_format($totalWorst, 2, '.', ''),
            ],
            'first_target_row' => $seriesTarget[0] ?? null,
        ];

        if ($debug) {
            logger()->info('BCS DEBUG', $debugPayload);
        }

        return response()->json([
            'message' => 'Simulation berechnet.',
            'basis'   => [
                'totalSalesYTD' => $totalSalesYTD,
                'ytdPct'        => (float) number_format($ytdPct, 2, '.', ''),
                'baseForecast'  => (float) number_format($baseForecast, 2, '.', ''),
            ],
            'seriesTarget'  => $seriesTarget,
            'seriesCurrent' => $seriesCurrent,
            'debug'         => $debug ? $debugPayload : null,
        ])->header('X-BCS-Trace-Id', $traceId)
          ->header('X-BCS-Debug', json_encode($debugPayload, JSON_UNESCAPED_UNICODE));
    }

    private function fiscalYearFromDate(Carbon $date): int
    {
        $fy = $date->year;
        if ($date->month < self::FISCAL_START_MONTH) $fy -= 1;
        return $fy;
    }

    // Map a seasonality row to ['Jan'=>float,...,'Dec'=>float]; supports 'jan'..'dec' or 'Jan'..'Dec'
    private function mapSeasonRow(object $row): array
    {
        $out = [];
        $map = [
            'Jan'=>'jan','Feb'=>'feb','Mar'=>'mar','Apr'=>'apr','May'=>'may','Jun'=>'jun',
            'Jul'=>'jul','Aug'=>'aug','Sep'=>'sep','Oct'=>'oct','Nov'=>'nov','Dec'=>'dec',
        ];
        foreach ($map as $cap => $low) {
            $val = isset($row->{$low}) ? $row->{$low} : (isset($row->{$cap}) ? $row->{$cap} : 0.0);
            // normalize comma decimals if stored as strings like "7,41"
            if (is_string($val)) {
                $val = str_replace(',', '.', $val);
            }
            $out[$cap] = (float) $val;
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

    public function dump(Request $request)
    {
        $data = $request->validate([
            'client_profit_center_id' => ['required','integer','exists:client_profit_centers,id'],
            'debug'                   => ['nullable','boolean'],
        ]);

        $traceId      = (string) Str::uuid();
        $cpcId        = (int) $data['client_profit_center_id'];
        $now          = Carbon::now();
        $capMonth     = max(1, min(12, $now->month - 1)); // last COMPLETE month
        $calendarYear = (int) $now->year;

        // --- Resolve profit_center_code FOR THIS CPC (so it changes when CPC changes)
        $pcCode = DB::table('client_profit_centers')
            ->where('id', $cpcId)
            ->value('profit_center_code');

        if (!$pcCode) {
            return response()->json([
                'message' => 'Profitcenter nicht gefunden.',
                'error'   => ['client_profit_center_id' => $cpcId],
            ], 422)->header('X-Dump-Trace', $traceId);
        }

        // --- Seasonality rows per PC
        $seasonMaxRow = DB::table('seasonalities')
            ->where('profit_center_code', $pcCode)
            ->orderBy('fiscal_year', 'desc')
            ->first();

        if (!$seasonMaxRow) {
            return response()->json([
                'message' => 'Saisonalitätsdaten fehlen.',
                'error'   => ['profit_center_code' => $pcCode],
            ], 422)->header('X-Dump-Trace', $traceId);
        }

        $seasonPrevRow = DB::table('seasonalities')
            ->where('profit_center_code', $pcCode)
            ->where('fiscal_year', '<', $seasonMaxRow->fiscal_year)
            ->orderBy('fiscal_year', 'desc')
            ->first();

        $seasonMaxFY  = (int) $seasonMaxRow->fiscal_year;
        $seasonPrevFY = (int) ($seasonPrevRow->fiscal_year ?? ($seasonMaxFY - 1));

        // Map rows to month->pct (accept comma decimals "7,41")
        $seasonMax  = $this->seasonRowToArray($seasonMaxRow);
        $seasonPrev = $this->seasonRowToArray($seasonPrevRow ?? $seasonMaxRow);

        // --- Sales YTD: JAN..cap of CURRENT calendar year
        $salesRows = DB::table('sales')
            ->select('month', DB::raw('SUM(volume) AS v'))
            ->where('client_profit_center_id', $cpcId)
            ->where('fiscal_year', $calendarYear)
            ->whereBetween('month', [1, $capMonth])
            ->groupBy('month')
            ->pluck('v', 'month');

        $salesByMonth = [];
        $salesYTD = 0;
        for ($m = 1; $m <= $capMonth; $m++) {
            $val = (int) round((float)($salesRows[$m] ?? 0), 0);
            $salesByMonth[$m] = $val;
            $salesYTD += $val;
        }

        // --- Saison-Anteil YTD: JAN..MAR from prev FY + APR..cap from max FY
        $names = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
        $seasonByMonth = [];
        $seasonYTD = 0.0;

        for ($m = 1; $m <= $capMonth; $m++) {
            $name = $names[$m];
            $pct  = ($m <= 3) ? ($seasonPrev[$name] ?? 0.0) : ($seasonMax[$name] ?? 0.0);
            $pct  = (float) number_format($pct, 2, '.', '');
            $seasonByMonth[$m] = $pct;
            $seasonYTD += $pct;
        }

        // --- Base Forecast
        $baseForecast = ($seasonYTD > 0) ? ($salesYTD / ($seasonYTD / 100.0)) : 0.0;

        // Build dump payload
        $dump = [
            'trace_id'       => $traceId,
            'inputs'         => [
                'client_profit_center_id' => $cpcId,
                'profit_center_code'      => $pcCode,
                'calendar_year'           => $calendarYear,
                'cap_month'               => $capMonth,
            ],
            'seasonality'    => [
                'max_fiscal_year'  => $seasonMaxFY,
                'prev_fiscal_year' => $seasonPrevFY,
                'ytd_by_month'     => $seasonByMonth,             // {1:Jan%, 2:Feb%, ...}
                'ytd_sum_pct'      => (float) number_format($seasonYTD, 2, '.', ''), // e.g. 49.32
            ],
            'sales'          => [
                'ytd_by_month'     => $salesByMonth,              // {1:vol, 2:vol, ...}
                'ytd_sum_volume'   => (int) $salesYTD,            // FULL integer
            ],
            'base_forecast'  => (float) number_format($baseForecast, 2, '.', ''), // e.g. 39293.12
        ];

        // Return JSON + headers so you can console.log headers in the browser too
        return response()->json([
            'message' => 'Dump erstellt.',
            'dump'    => $dump,
        ])->header('X-Dump-Trace', $traceId)
          ->header('X-Dump', json_encode($dump, JSON_UNESCAPED_UNICODE));
    }

    /** Map one seasonality row into ['Apr'=>float,...,'Mar'=>float] with decimal normalization. */
    private function seasonRowToArray(object $row): array
    {
        $cols = ['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar'];
        $out  = [];
        foreach ($cols as $c) {
            $v = $row->{$c} ?? 0.0;
            if (is_string($v)) $v = str_replace(',', '.', $v);
            $out[$c] = (float) $v;
        }
        return $out;
    }
}