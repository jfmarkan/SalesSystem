<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Dashboard data endpoint.
     * - Base unit: VKEH (raw ERP "volume")
     * - Period: from April 1st of the fiscal year up to the last FULL month (see "lastFull" rule)
     * - Filters: only Client+PC (CPC) assigned to the logged-in user (assignments.user_id)
     * - Aggregations by profit_center_code
     * - KPIs:
     *      1) Ist vs Prognose (%)      = ((sales_m3 / forecast_m3) - 1) * 100, with delta_m3
     *      2) Ist vs Budget (%)        = ((sales_m3 / budget_m3)   - 1) * 100, with delta_m3
     *      3) Gesamt-Ist (€)           = sum(sales_vkeh * factor_to_euro)
     *      4) Zusatzquote (m³)         = placeholder (0)
     * - Calendar: action_items joined with action_plans for current user within the period
     * - Returns ONLY PCs with any activity (sales/forecast/budget > 0) to reduce payload
     * - All UI strings are German; code/comment identifiers in English.
     */
    public function index(Request $req)
    {
        try {
            // --- auth
            $authId = Auth::id();
            if (!$authId) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            // --- tables presence flags
            $has = fn(string $t) => Schema::hasTable($t);
            $tables = [
                'profit_centers'        => $has('profit_centers'),
                'client_profit_centers' => $has('client_profit_centers'),
                'assignments'           => $has('assignments'),
                'sales'                 => $has('sales'),
                'forecasts'             => $has('forecasts'),
                'budgets'               => $has('budgets'),
                'unit_conversions'      => $has('unit_conversions'),
                'action_items'          => $has('action_items'),
                'action_plans'          => $has('action_plans'),
            ];

            // --- period range: Apr 1st .. last FULL month
            // rule: if today->day >= 4 => lastFull = previous month; else lastFull = previous-1 month (two months back)
            $today = Carbon::today();
            $lastFull = $today->day >= 4 ? $today->copy()->subMonthNoOverflow() : $today->copy()->subMonthsNoOverflow(2);
            // fiscal year start at April
            $startFYYear = $lastFull->month >= 4 ? $lastFull->year : ($lastFull->year - 1);
            $periodStart = Carbon::create($startFYYear, 4, 1)->startOfDay();
            $periodEnd   = $lastFull->copy()->endOfMonth()->endOfDay();
            // for compact range comparisons yyyyMM key
            $startKey = (int)($periodStart->year * 100 + 4);
            $endKey   = (int)($periodEnd->year * 100 + $periodEnd->month);

            // --- assigned CPCs for this user
            if (!$tables['assignments'] || !$tables['client_profit_centers']) {
                return response()->json([
                    'message' => 'Required tables missing',
                    'missing' => array_keys(array_filter($tables, fn($v) => !$v)),
                ], 500);
            }

            $cpcIds = DB::table('assignments')
                ->where('user_id', $authId)
                ->whereNull('deleted_at')
                ->pluck('client_profit_center_id')
                ->unique()
                ->values();

            if ($cpcIds->isEmpty()) {
                // no assignments => empty dashboard
                return response()->json([
                    'period'   => $lastFull->format('Y-m'),
                    'unit'     => 'VKEH',
                    'kpis'     => ['items'=>[], 'period'=>$lastFull->format('Y-m')],
                    'chart'    => ['pc_codes'=>[], 'labels'=>[], 'series'=>[], 'unit'=>'VKEH'],
                    'table'    => ['rows'=>[], 'totals'=>['sales'=>0,'forecast'=>0,'budget'=>0,'unit'=>'VKEH']],
                    'calendar' => ['from'=>$periodStart->toDateString(),'to'=>$periodEnd->toDateString(),'events'=>[]],
                ]);
            }

            // --- collect conversions per PC
            $factorM3  = [];
            $factorEUR = [];
            if ($tables['unit_conversions']) {
                $rows = DB::table('unit_conversions')
                    ->select('profit_center_code','factor_to_m3','factor_to_euro')
                    ->get();
                foreach ($rows as $r) {
                    $code = (string)$r->profit_center_code;
                    $factorM3[$code]  = (float)($r->factor_to_m3  ?? 1);
                    $factorEUR[$code] = (float)($r->factor_to_euro ?? 1);
                }
            }

            // --- name column on profit_centers (handle typo variant)
            $pcNameCol = 'profit_center_code';
            if ($tables['profit_centers']) {
                if (Schema::hasColumn('profit_centers', 'profit_center_name')) {
                    $pcNameCol = 'profit_center_name';
                } elseif (Schema::hasColumn('profit_centers', 'prfit_center_name')) {
                    $pcNameCol = 'prfit_center_name';
                }
            }

            // --- aggregation helpers (yyyyMM between startKey..endKey)
            $ymBetween = function(string $alias, string $yearCol='fiscal_year', string $monCol='month') {
                return DB::raw("(($alias.$yearCol*100)+$alias.$monCol)");
            };

            // SALES (sum volume by pc)
            $salesAgg = collect();
            if ($tables['sales']) {
                $salesAgg = DB::table('sales as s')
                    ->join('client_profit_centers as cpc', 'cpc.id', '=', 's.client_profit_center_id')
                    ->join('profit_centers as pc', 'pc.profit_center_code', '=', 'cpc.profit_center_code')
                    ->whereIn('s.client_profit_center_id', $cpcIds)
                    ->whereNull('s.deleted_at')
                    ->whereNull('cpc.deleted_at')
                    ->whereBetween($ymBetween('s'), [$startKey, $endKey])
                    ->groupBy('pc.profit_center_code')
                    ->select('pc.profit_center_code as code', DB::raw('SUM(s.volume) as v'))
                    ->get();
            }

            // FORECASTS (latest version per cpc/year/month; sum by pc)
            $fcAgg = collect();
            if ($tables['forecasts']) {
                $sub = DB::table('forecasts as fx')
                    ->select('client_profit_center_id', 'fiscal_year', 'month', DB::raw('MAX(version) as v'))
                    ->whereIn('client_profit_center_id', $cpcIds)
                    ->whereNull('deleted_at')
                    ->whereBetween($ymBetween('fx'), [$startKey, $endKey])
                    ->groupBy('client_profit_center_id','fiscal_year','month');

                $fcAgg = DB::table('forecasts as f')
                    ->joinSub($sub, 'lv', function($j) {
                        $j->on('lv.client_profit_center_id', '=', 'f.client_profit_center_id')
                          ->on('lv.fiscal_year', '=', 'f.fiscal_year')
                          ->on('lv.month', '=', 'f.month')
                          ->on('lv.v', '=', 'f.version');
                    })
                    ->join('client_profit_centers as cpc', 'cpc.id', '=', 'f.client_profit_center_id')
                    ->join('profit_centers as pc', 'pc.profit_center_code', '=', 'cpc.profit_center_code')
                    ->whereIn('f.client_profit_center_id', $cpcIds)
                    ->whereNull('f.deleted_at')
                    ->whereNull('cpc.deleted_at')
                    ->whereBetween($ymBetween('f'), [$startKey, $endKey])
                    ->groupBy('pc.profit_center_code')
                    ->select('pc.profit_center_code as code', DB::raw('SUM(f.volume) as v'))
                    ->get();
            }

            // BUDGETS (sum by pc)
            $budAgg = collect();
            if ($tables['budgets']) {
                $budAgg = DB::table('budgets as b')
                    ->join('client_profit_centers as cpc', 'cpc.id', '=', 'b.client_profit_center_id')
                    ->join('profit_centers as pc', 'pc.profit_center_code', '=', 'cpc.profit_center_code')
                    ->whereIn('b.client_profit_center_id', $cpcIds)
                    ->whereNull('b.deleted_at')
                    ->whereNull('cpc.deleted_at')
                    ->whereBetween($ymBetween('b'), [$startKey, $endKey])
                    ->groupBy('pc.profit_center_code')
                    ->select('pc.profit_center_code as code', DB::raw('SUM(b.volume) as v'))
                    ->get();
            }

            // --- union of pc codes with any data
            $codesSet = collect()
                ->merge($salesAgg->pluck('code'))
                ->merge($fcAgg->pluck('code'))
                ->merge($budAgg->pluck('code'))
                ->unique()
                ->values();

            // --- pc meta (code->name)
            $pcMeta = [];
            if ($tables['profit_centers'] && $codesSet->isNotEmpty()) {
                $pcRows = DB::table('profit_centers')
                    ->whereIn('profit_center_code', $codesSet)
                    ->select('profit_center_code as code', DB::raw($pcNameCol . ' as name'))
                    ->get();
                foreach ($pcRows as $r) $pcMeta[(string)$r->code] = $r->name;
            }

            // --- metrics per pc (VKEH)
            $metrics = [];
            foreach ($codesSet as $code) {
                $metrics[(string)$code] = ['sales_vkeh'=>0.0,'forecast_vkeh'=>0.0,'budget_vkeh'=>0.0];
            }
            foreach ($salesAgg as $r)   { $metrics[(string)$r->code]['sales_vkeh']    = (float)$r->v; }
            foreach ($fcAgg as $r)      { $metrics[(string)$r->code]['forecast_vkeh'] = (float)$r->v; }
            foreach ($budAgg as $r)     { $metrics[(string)$r->code]['budget_vkeh']   = (float)$r->v; }

            // --- build pc list with names (only those with any non-zero)
            $pcs = collect();
            foreach ($codesSet as $code) {
                $code = (string)$code;
                $m = $metrics[$code];
                if (($m['sales_vkeh'] + $m['forecast_vkeh'] + $m['budget_vkeh']) == 0) continue;
                $pcs->push((object)[
                    'code' => $code,
                    'name' => $pcMeta[$code] ?? $code,
                ]);
            }
            // stable sort by code
            $pcs = $pcs->sortBy('code')->values();

            // --- sum helpers
            $sumFieldVKEH = function(string $field) use ($metrics,$pcs): float {
                $s = 0.0;
                foreach ($pcs as $pc) $s += ($metrics[$pc->code][$field.'_vkeh'] ?? 0.0);
                return $s;
            };
            $sumFieldM3 = function(string $field) use ($metrics,$pcs,$factorM3): float {
                $s = 0.0;
                foreach ($pcs as $pc) {
                    $v = ($metrics[$pc->code][$field.'_vkeh'] ?? 0.0);
                    $s += $v * ($factorM3[$pc->code] ?? 1.0);
                }
                return $s;
            };

            // --- chart (VKEH)
            $labels=[]; $codes=[]; $sIst=[]; $sProg=[]; $sBud=[];
            foreach ($pcs as $pc) {
                $labels[] = $pc->name;
                $codes[]  = $pc->code;
                $sIst[]   = round($metrics[$pc->code]['sales_vkeh'], 4);
                $sProg[]  = round($metrics[$pc->code]['forecast_vkeh'], 4);
                $sBud[]   = round($metrics[$pc->code]['budget_vkeh'], 4);
            }
            $chart = [
                'pc_codes' => $codes,
                'labels'   => $labels,
                'series'   => [
                    ['name'=>'Ist','data'=>$sIst],
                    ['name'=>'Prognose','data'=>$sProg],
                    ['name'=>'Budget','data'=>$sBud],
                ],
                'unit' => 'VKEH',
            ];

            // --- table (VKEH) without pct columns
            $tableRows = [];
            foreach ($pcs as $pc) {
                $tableRows[] = [
                    'pc_code'  => $pc->code,
                    'pc_name'  => $pc->name,
                    'sales'    => round($metrics[$pc->code]['sales_vkeh'], 4),
                    'forecast' => round($metrics[$pc->code]['forecast_vkeh'], 4),
                    'budget'   => round($metrics[$pc->code]['budget_vkeh'], 4),
                ];
            }
            $tableTotals = [
                'sales'    => round($sumFieldVKEH('sales'), 4),
                'forecast' => round($sumFieldVKEH('forecast'), 4),
                'budget'   => round($sumFieldVKEH('budget'), 4),
                'unit'     => 'VKEH',
            ];

            // --- KPI calculations (m³ & EUR)
            $sales_m3    = $sumFieldM3('sales');
            $budget_m3   = $sumFieldM3('budget');
            $forecast_m3 = $sumFieldM3('forecast');

            $sumFieldEUR = function(string $field) use ($metrics,$pcs,$factorEUR): float {
                $s = 0.0;
                foreach ($pcs as $pc) {
                    $v = ($metrics[$pc->code][$field.'_vkeh'] ?? 0.0);
                    $s += $v * ($factorEUR[$pc->code] ?? 1.0);
                }
                return $s;
            };
            $sales_eur = $sumFieldEUR('sales');

            $perf_fc_pct  = $forecast_m3 > 0 ? (($sales_m3 / $forecast_m3) - 1.0) * 100.0 : 0.0;
            $perf_bud_pct = $budget_m3   > 0 ? (($sales_m3 / $budget_m3)   - 1.0) * 100.0 : 0.0;
            $delta_fc_m3  = $sales_m3 - $forecast_m3;
            $delta_bud_m3 = $sales_m3 - $budget_m3;

            $kpis = [
                'items' => [
                    [
                        'id'     => 'perf_vs_forecast_pct',
                        'label'  => 'Ist vs Prognose (%)',
                        'value'  => round($perf_fc_pct, 2),
                        'unit'   => '%',
                        'details'=> ['delta_m3' => round($delta_fc_m3, 2)],
                    ],
                    [
                        'id'     => 'perf_vs_budget_pct',
                        'label'  => 'Ist vs Budget (%)',
                        'value'  => round($perf_bud_pct, 2),
                        'unit'   => '%',
                        'details'=> ['delta_m3' => round($delta_bud_m3, 2)],
                    ],
                    [
                        'id'    => 'sales_total_eur',
                        'label' => 'Gesamt-Ist (€)',
                        'value' => round($sales_eur, 2),
                        'unit'  => '€',
                    ],
                    [
                        'id'    => 'extra_quota_m3',
                        'label' => 'Zusatzquote (m³)',
                        'value' => 0.0, // placeholder until source is defined
                        'unit'  => 'm³',
                    ],
                ],
                'period' => $lastFull->format('Y-m'),
            ];

            // --- Calendar: action_items + action_plans of current user in same period
            $events = collect();
            if ($tables['action_items'] && $tables['action_plans']) {
                $rowsCal = DB::table('action_items as ai')
                    ->join('action_plans as ap','ap.id','=','ai.action_plan_id')
                    ->select('ai.id','ai.title','ai.description','ai.due_date','ap.deviation_id','ap.user_id','ap.objective')
                    ->whereNotNull('ai.due_date')
                    ->where('ap.user_id', $authId)
                    ->whereBetween('ai.due_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->orderBy('ai.due_date')
                    ->get();

                $todayStr = Carbon::today()->toDateString();
                $events = $rowsCal->map(fn($r)=>[
                    'id'     => $r->id,
                    'title'  => $r->title,
                    'content'=> $r->description,
                    'start'  => $r->due_date,
                    'end'    => $r->due_date,
                    'status' => $r->due_date < $todayStr ? 'overdue' : 'pending',
                    'meta'   => [
                        'deviation_id' => $r->deviation_id,
                        'user_id'      => $r->user_id,
                        'objective'    => $r->objective,
                    ],
                ]);
            }

            // --- payload
            $payload = [
                'period'   => $lastFull->format('Y-m'),
                'unit'     => 'VKEH',
                'kpis'     => $kpis,
                'chart'    => $chart,
                'table'    => ['rows'=>$tableRows, 'totals'=>$tableTotals, 'unit'=>'VKEH'],
                'calendar' => ['from'=>$periodStart->toDateString(), 'to'=>$periodEnd->toDateString(), 'events'=>$events],
            ];

            // optional debug dump
            if ($req->boolean('debug')) {
                $payload['debug'] = [
                    'today'      => $today->toDateString(),
                    'lastFull'   => $lastFull->toDateString(),
                    'periodFrom' => $periodStart->toDateString(),
                    'periodTo'   => $periodEnd->toDateString(),
                    'keys'       => ['startKey'=>$startKey,'endKey'=>$endKey],
                    'counts'     => [
                        'assignments' => $cpcIds->count(),
                        'pcs'         => $pcs->count(),
                        'salesAgg'    => $salesAgg->count(),
                        'fcAgg'       => $fcAgg->count(),
                        'budAgg'      => $budAgg->count(),
                        'events'      => $events->count(),
                    ],
                ];
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Dashboard error', ['exception' => $e]);
            return response()->json(['message' => 'Server error'], 500);
        }
    }
}