<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Dashboard endpoint
     * - Base unit: VKEH (ventas en unidades de venta)
     * - Window: desde abril hasta el último mes completo del período de referencia (?period=YYYY-MM). Si no se pasa, se toma el mes actual.
     * - Filtra por CPC asignados al usuario logueado (assignments.user_id = Auth::id()).
     * - Forecast: toma la última versión del usuario logueado por CPC/mes dentro del rango.
     * - Devuelve datos en VKEH y además factores por PC para convertir en el front a m³/€.
     * - UI strings en alemán (Ist, Prognose, Budget, etc.).
     */
    public function index(Request $req)
    {
        try {
            // -----------------------------------------------------------------
            // Inputs
            // -----------------------------------------------------------------
            $uid = Auth::id();
            if (!$uid) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $requestedUnit = strtoupper($req->query('unit', 'VKEH'));
            if (!in_array($requestedUnit, ['VKEH', 'M3', 'EUR'])) {
                $requestedUnit = 'VKEH';
            }

            // Reference period (YYYY-MM). We compute the "last complete month" relative to this.
            $period = $req->query('period', Carbon::now()->format('Y-m'));
            $ref = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
            $lastComplete = $ref->copy()->subMonthNoOverflow(); // last complete month
            // Start from April of the appropriate year
            $startYear = $lastComplete->month >= 4 ? $lastComplete->year : ($lastComplete->year - 1);
            $rangeStart = Carbon::create($startYear, 4, 1)->startOfDay();
            $rangeEnd   = $lastComplete->copy()->endOfMonth();

            // Build allowed (YYYYMM) keys for filtering quickly
            $periodKeys = [];
            $cursor = $rangeStart->copy();
            while ($cursor->lte($rangeEnd)) {
                $periodKeys[] = (int)($cursor->year * 100 + $cursor->month);
                $cursor->addMonth();
            }

            // -----------------------------------------------------------------
            // Safety: check required tables exist
            // -----------------------------------------------------------------
            $hasAssignments = Schema::hasTable('assignments');
            $hasCPC         = Schema::hasTable('client_profit_centers');
            $hasSales       = Schema::hasTable('sales');
            $hasForecasts   = Schema::hasTable('forecasts');
            $hasBudgets     = Schema::hasTable('budgets');
            $hasPC          = Schema::hasTable('profit_centers');
            $hasUnitConv    = Schema::hasTable('unit_conversions');
            $hasActions     = Schema::hasTable('action_items') && Schema::hasTable('action_plans');

            if (!$hasAssignments || !$hasCPC || !$hasSales || !$hasForecasts || !$hasBudgets) {
                return response()->json(['message' => 'Server error: missing tables'], 500);
            }

            // -----------------------------------------------------------------
            // Resolve CPCs assigned to the current user
            // -----------------------------------------------------------------
            $assigned = DB::table('assignments AS a')
                ->join('client_profit_centers AS cpc', 'cpc.id', '=', 'a.client_profit_center_id')
                ->select('a.client_profit_center_id AS cpc_id', 'cpc.profit_center_code')
                ->where('a.user_id', $uid)
                ->whereNull('a.deleted_at')
                ->whereNull('cpc.deleted_at')
                ->get();

            if ($assigned->isEmpty()) {
                // Still return a valid payload with empty data
                return response()->json($this->emptyPayload($period, $requestedUnit, $rangeStart, $rangeEnd));
            }

            $cpcIds = $assigned->pluck('cpc_id')->all(); // array of CPC ids
            $pcCodesByCpc = [];
            foreach ($assigned as $r) {
                $pcCodesByCpc[$r->cpc_id] = (string)$r->profit_center_code;
            }

            // -----------------------------------------------------------------
            // Load profit center names for used codes (after we know which codes appear)
            // -----------------------------------------------------------------
            $pcNameCol = 'profit_center_code'; // fallback
            $pcLabelCol = null;
            if ($hasPC) {
                // Try to find a usable name column
                if (Schema::hasColumn('profit_centers', 'profit_center_name')) {
                    $pcLabelCol = 'profit_center_name';
                } elseif (Schema::hasColumn('profit_centers', 'prfit_center_name')) {
                    $pcLabelCol = 'prfit_center_name';
                }
            }

            // -----------------------------------------------------------------
            // Helper for WHERE period range (by fiscal_year / month)
            // We'll compare (fiscal_year * 100 + LPAD(month,2)) against allowed keys.
            // -----------------------------------------------------------------
            $makePeriodWhere = function ($alias = 't') use ($periodKeys) {
                // MySQL-friendly numeric concat: fiscal_year*100 + month
                return function ($q) use ($alias, $periodKeys) {
                    $q->whereIn(DB::raw("({$alias}.fiscal_year*100 + {$alias}.month)"), $periodKeys);
                };
            };

            // -----------------------------------------------------------------
            // SALES (sum volume) for assigned CPCs within window
            // -----------------------------------------------------------------
            $salesRows = DB::table('sales AS s')
                ->join('client_profit_centers AS cpc', 'cpc.id', '=', 's.client_profit_center_id')
                ->join('assignments AS a', 'a.client_profit_center_id', '=', 'cpc.id')
                ->select('cpc.profit_center_code', DB::raw('SUM(s.volume) AS vkeh'))
                ->where('a.user_id', $uid)
                ->whereNull('s.deleted_at')
                ->whereNull('a.deleted_at')
                ->whereNull('cpc.deleted_at')
                ->whereIn('s.client_profit_center_id', $cpcIds)
                ->where($makePeriodWhere('s'))
                ->groupBy('cpc.profit_center_code')
                ->get();

            // -----------------------------------------------------------------
            // FORECASTS (latest version per (CPC, year, month) for this user), then sum
            // -----------------------------------------------------------------
            $latestForecast = DB::table('forecasts AS f1')
                ->select('f1.client_profit_center_id', 'f1.fiscal_year', 'f1.month', DB::raw('MAX(f1.version) AS max_version'))
                ->whereNull('f1.deleted_at')
                ->where('f1.user_id', $uid)
                ->whereIn('f1.client_profit_center_id', $cpcIds)
                ->where($makePeriodWhere('f1'))
                ->groupBy('f1.client_profit_center_id', 'f1.fiscal_year', 'f1.month');

            $forecastRows = DB::table('forecasts AS f')
                ->joinSub($latestForecast, 'lf', function ($join) {
                    $join->on('lf.client_profit_center_id', '=', 'f.client_profit_center_id')
                        ->on('lf.fiscal_year', '=', 'f.fiscal_year')
                        ->on('lf.month', '=', 'f.month')
                        ->on('lf.max_version', '=', 'f.version');
                })
                ->join('client_profit_centers AS cpc', 'cpc.id', '=', 'f.client_profit_center_id')
                ->select('cpc.profit_center_code', DB::raw('SUM(f.volume) AS vkeh'))
                ->whereNull('f.deleted_at')
                ->whereNull('cpc.deleted_at')
                ->groupBy('cpc.profit_center_code')
                ->get();

            // -----------------------------------------------------------------
            // BUDGETS (sum volume) for assigned CPCs within window
            // -----------------------------------------------------------------
            $budgetRows = DB::table('budgets AS b')
                ->join('client_profit_centers AS cpc', 'cpc.id', '=', 'b.client_profit_center_id')
                ->join('assignments AS a', 'a.client_profit_center_id', '=', 'cpc.id')
                ->select('cpc.profit_center_code', DB::raw('SUM(b.volume) AS vkeh'))
                ->where('a.user_id', $uid)
                ->whereNull('b.deleted_at')
                ->whereNull('a.deleted_at')
                ->whereNull('cpc.deleted_at')
                ->whereIn('b.client_profit_center_id', $cpcIds)
                ->where($makePeriodWhere('b'))
                ->groupBy('cpc.profit_center_code')
                ->get();

            // -----------------------------------------------------------------
            // Merge metric maps by PC code
            // -----------------------------------------------------------------
            $byPC = []; // code => ['sales_vkeh'=>, 'forecast_vkeh'=>, 'budget_vkeh'=>]
            foreach ($salesRows as $r) {
                $code = (string)$r->profit_center_code;
                $byPC[$code]['sales_vkeh'] = ($byPC[$code]['sales_vkeh'] ?? 0) + (float)$r->vkeh;
            }
            foreach ($forecastRows as $r) {
                $code = (string)$r->profit_center_code;
                $byPC[$code]['forecast_vkeh'] = ($byPC[$code]['forecast_vkeh'] ?? 0) + (float)$r->vkeh;
            }
            foreach ($budgetRows as $r) {
                $code = (string)$r->profit_center_code;
                $byPC[$code]['budget_vkeh'] = ($byPC[$code]['budget_vkeh'] ?? 0) + (float)$r->vkeh;
            }

            // Keep only PCs that have any value > 0 (optimization requested)
            $byPC = array_filter($byPC, function ($m) {
                $s = (float)($m['sales_vkeh'] ?? 0);
                $f = (float)($m['forecast_vkeh'] ?? 0);
                $b = (float)($m['budget_vkeh'] ?? 0);
                return ($s > 0) || ($f > 0) || ($b > 0);
            });

            if (empty($byPC)) {
                // No data in the window
                return response()->json($this->emptyPayload($period, $requestedUnit, $rangeStart, $rangeEnd));
            }

            $pcCodes = array_keys($byPC);

            // -----------------------------------------------------------------
            // Load PC names for these codes
            // -----------------------------------------------------------------
            $pcNames = [];
            if ($hasPC && $pcLabelCol) {
                $rows = DB::table('profit_centers')
                    ->select('profit_center_code', DB::raw("$pcLabelCol AS name"))
                    ->whereIn('profit_center_code', $pcCodes)
                    ->get();
                foreach ($rows as $r) {
                    $pcNames[(string)$r->profit_center_code] = (string)$r->name;
                }
            }
            // Fallback: name = code
            foreach ($pcCodes as $code) {
                if (!isset($pcNames[$code])) $pcNames[$code] = $code;
            }

            // -----------------------------------------------------------------
            // Unit conversions for these PCs
            // -----------------------------------------------------------------
            $factorM3 = [];
            $factorEUR = [];
            if ($hasUnitConv) {
                $convRows = DB::table('unit_conversions')
                    ->select('profit_center_code', 'factor_to_m3', 'factor_to_euro')
                    ->whereIn('profit_center_code', $pcCodes)
                    ->get();
                foreach ($convRows as $r) {
                    $code = (string)$r->profit_center_code;
                    $factorM3[$code]  = (float)($r->factor_to_m3 ?? 1);
                    $factorEUR[$code] = (float)($r->factor_to_euro ?? 1);
                }
            }
            // Default factors = 1
            foreach ($pcCodes as $code) {
                if (!isset($factorM3[$code]))  $factorM3[$code]  = 1.0;
                if (!isset($factorEUR[$code])) $factorEUR[$code] = 1.0;
            }

            // -----------------------------------------------------------------
            // Helpers to convert per-PC values from VKEH -> requested
            // -----------------------------------------------------------------
            $toUnit = function (string $pcCode, float $vkeh) use ($requestedUnit, $factorM3, $factorEUR): float {
                if ($requestedUnit === 'VKEH') return $vkeh;
                if ($requestedUnit === 'M3')   return $vkeh * ($factorM3[$pcCode] ?? 1.0);
                if ($requestedUnit === 'EUR')  return $vkeh * ($factorEUR[$pcCode] ?? 1.0);
                return $vkeh;
            };

            // -----------------------------------------------------------------
            // Build chart & table
            // -----------------------------------------------------------------
            // Sorted by PC code (stable)
            sort($pcCodes, SORT_NATURAL);

            $chartLabels = [];
            $chartCodes  = [];
            $seriesSales    = [];
            $seriesForecast = [];
            $seriesBudget   = [];

            $tableRows = [];
            $totSales = 0.0; $totForecast = 0.0; $totBudget = 0.0;

            foreach ($pcCodes as $code) {
                $m = $byPC[$code] ?? [];
                $s_v = (float)($m['sales_vkeh'] ?? 0);
                $f_v = (float)($m['forecast_vkeh'] ?? 0);
                $b_v = (float)($m['budget_vkeh'] ?? 0);

                $chartLabels[] = $pcNames[$code] ?? $code;
                $chartCodes[]  = $code;

                $seriesSales[]    = round($toUnit($code, $s_v), 4);
                $seriesForecast[] = round($toUnit($code, $f_v), 4);
                $seriesBudget[]   = round($toUnit($code, $b_v), 4);

                $tableRows[] = [
                    'pc_code'   => $code,
                    'pc_name'   => $pcNames[$code] ?? $code,
                    'sales'     => round($toUnit($code, $s_v), 4),
                    'forecast'  => round($toUnit($code, $f_v), 4),
                    'budget'    => round($toUnit($code, $b_v), 4),
                ];

                $totSales    += $toUnit($code, $s_v);
                $totForecast += $toUnit($code, $f_v);
                $totBudget   += $toUnit($code, $b_v);
            }

            $chart = [
                'labels' => $chartLabels,
                'pc_codes' => $chartCodes, // <- requested: codes for each label
                'series' => [
                    ['name' => 'Ist',       'data' => $seriesSales],
                    ['name' => 'Prognose',  'data' => $seriesForecast],
                    ['name' => 'Budget',    'data' => $seriesBudget],
                ],
                'unit' => $requestedUnit,
            ];

            $tableTotals = [
                'sales'    => round($totSales, 4),
                'forecast' => round($totForecast, 4),
                'budget'   => round($totBudget, 4),
                'unit'     => $requestedUnit,
            ];

            // -----------------------------------------------------------------
            // KPI calculations (all in window April..lastComplete)
            //  - Percent values: (Ist - X) / X * 100 (negative if short)
            //  - Delta m³: convert per PC (Ist - X)*factor_to_m3 then sum
            //  - EUR total from Ist: sum(Ist_vkeh * factor_to_euro)
            // -----------------------------------------------------------------
            $sumSalesVKEH = 0.0;
            $sumFcastVKEH = 0.0;
            $sumBudgetVKEH = 0.0;
            $sumDeltaSF_M3 = 0.0; // (Ist - Prognose) in m3
            $sumDeltaSB_M3 = 0.0; // (Ist - Budget) in m3
            $sumSalesEUR   = 0.0; // Ist in €
            foreach ($pcCodes as $code) {
                $s_v = (float)($byPC[$code]['sales_vkeh'] ?? 0);
                $f_v = (float)($byPC[$code]['forecast_vkeh'] ?? 0);
                $b_v = (float)($byPC[$code]['budget_vkeh'] ?? 0);
                $sumSalesVKEH  += $s_v;
                $sumFcastVKEH  += $f_v;
                $sumBudgetVKEH += $b_v;
                $sumDeltaSF_M3 += ($s_v - $f_v) * ($factorM3[$code] ?? 1);
                $sumDeltaSB_M3 += ($s_v - $b_v) * ($factorM3[$code] ?? 1);
                $sumSalesEUR   += $s_v * ($factorEUR[$code] ?? 1);
            }

            $pct_sf = ($sumFcastVKEH == 0.0) ? 0.0 : (($sumSalesVKEH - $sumFcastVKEH) / max($sumFcastVKEH, 1e-9)) * 100.0;
            $pct_sb = ($sumBudgetVKEH == 0.0) ? 0.0 : (($sumSalesVKEH - $sumBudgetVKEH) / max($sumBudgetVKEH, 1e-9)) * 100.0;

            $kpis = [
                'period' => $lastComplete->format('Y-m'),
                'items' => [
                    [
                        'id'    => 'ratio_ist_vs_prognose',
                        'label' => 'Ist vs. Prognose (%)',
                        'value' => round($pct_sf, 2),
                        'unit'  => '%',
                        'details' => [ 'delta_m3' => round($sumDeltaSF_M3, 2) ],
                    ],
                    [
                        'id'    => 'ratio_ist_vs_budget',
                        'label' => 'Ist vs. Budget (%)',
                        'value' => round($pct_sb, 2),
                        'unit'  => '%',
                        'details' => [ 'delta_m3' => round($sumDeltaSB_M3, 2) ],
                    ],
                    [
                        'id'    => 'abweichung_ist_prognose_m3',
                        'label' => 'Abweichung Ist–Prognose (m³)',
                        'value' => round($sumDeltaSF_M3, 2),
                        'unit'  => 'M3',
                    ],
                    [
                        'id'    => 'umsatz_eur',
                        'label' => 'Umsatz gesamt (€)',
                        'value' => round($sumSalesEUR, 2),
                        'unit'  => 'EUR',
                    ],
                ],
            ];

            // -----------------------------------------------------------------
            // Calendar events (Action Items within range)
            // -----------------------------------------------------------------
            $events = collect();
            if ($hasActions) {
                $rows = DB::table('action_items AS ai')
                    ->join('action_plans AS ap', 'ap.id', '=', 'ai.action_plan_id')
                    ->select('ai.id', 'ai.title', 'ai.description', 'ai.due_date', 'ap.deviation_id', 'ap.user_id', 'ap.objective')
                    ->whereBetween('ai.due_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                    ->orderBy('ai.due_date')
                    ->get();
                $today = Carbon::today()->toDateString();
                $events = $rows->map(function ($r) use ($today) {
                    return [
                        'id'     => $r->id,
                        'title'  => $r->title,
                        'content'=> $r->description,
                        'start'  => $r->due_date,
                        'end'    => $r->due_date,
                        'status' => ($r->due_date < $today) ? 'overdue' : 'pending',
                        'meta'   => [
                            'deviation_id' => $r->deviation_id,
                            'user_id'      => $r->user_id,
                            'objective'    => $r->objective,
                        ],
                    ];
                });
            }

            // -----------------------------------------------------------------
            // Extra (Zusatzquote) – placeholder until schema is provided
            // -----------------------------------------------------------------
            $extra = [
                'label'  => 'Zusatzquote',
                'period' => $lastComplete->format('Y-m'),
                'total'  => 0,
                'unit'   => 'M3',
                'by_pc'  => [],
            ];

            // -----------------------------------------------------------------
            // Response payload
            // -----------------------------------------------------------------
            $payload = [
                'period' => $period,
                'unit'   => $requestedUnit,
                'kpis'   => $kpis,
                'chart'  => $chart,
                'table'  => [
                    'rows'   => $tableRows,
                    'totals' => $tableTotals,
                    'unit'   => $requestedUnit,
                ],
                'calendar' => [
                    'from'   => $rangeStart->toDateString(),
                    'to'     => $rangeEnd->toDateString(),
                    'events' => $events,
                ],
                'extra' => $extra,
                // Conversions for front-end unit switching (base: VKEH)
                'conversions' => [
                    'base'    => 'VKEH',
                    'to_m3'   => $factorM3,
                    'to_euro' => $factorEUR,
                ],
            ];

            // Optional debug block
            if ((int)$req->query('debug', 0) === 1) {
                $payload['debug'] = [
                    'window' => [
                        'from' => $rangeStart->toDateString(),
                        'to'   => $rangeEnd->toDateString(),
                        'last_complete_month' => $lastComplete->format('Y-m'),
                    ],
                    'counts' => [
                        'assigned_cpc' => count($cpcIds),
                        'pc_codes'     => count($pcCodes),
                        'sales_rows'   => count($salesRows),
                        'forecast_rows'=> count($forecastRows),
                        'budget_rows'  => count($budgetRows),
                        'calendar'     => $events->count(),
                    ],
                ];
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Dashboard error', ['e' => $e]);
            return response()->json(['message' => 'Server error'], 500);
        }
    }

    /**
     * Empty payload helper (no data in window / no assignments).
     */
    private function emptyPayload(string $period, string $unit, Carbon $from, Carbon $to): array
    {
        return [
            'period' => $period,
            'unit'   => $unit,
            'kpis'   => [
                'period' => $to->format('Y-m'),
                'items'  => [
                    ['id'=>'ratio_ist_vs_prognose','label'=>'Ist vs. Forecsat','value'=>0,'unit'=>'%','details'=>['delta_m3'=>0]],
                    ['id'=>'ratio_ist_vs_budget','label'=>'Ist vs. Budget','value'=>0,'unit'=>'%','details'=>['delta_m3'=>0]],
                    ['id'=>'abweichung_ist_prognose_m3','label'=>'Abweichung Ist – Forecast (m³)','value'=>0,'unit'=>'M3'],
                    ['id'=>'umsatz_eur','label'=>'Umsatz gesamt (€)','value'=>0,'unit'=>'EUR'],
                ],
            ],
            'chart'  => [
                'labels'   => [],
                'pc_codes' => [],
                'series'   => [
                    ['name'=>'Ist','data'=>[]],
                    ['name'=>'Prognose','data'=>[]],
                    ['name'=>'Budget','data'=>[]],
                ],
                'unit' => $unit,
            ],
            'table'  => [
                'rows'   => [],
                'totals' => ['sales'=>0,'forecast'=>0,'budget'=>0,'unit'=>$unit],
                'unit'   => $unit,
            ],
            'calendar' => [
                'from'   => $from->toDateString(),
                'to'     => $to->toDateString(),
                'events' => [],
            ],
            'extra' => [
                'label'  => 'Zusatzquote',
                'period' => $to->format('Y-m'),
                'total'  => 0,
                'unit'   => 'M3',
                'by_pc'  => [],
            ],
            'conversions' => [
                'base'    => 'VKEH',
                'to_m3'   => [],
                'to_euro' => [],
            ],
        ];
    }
}