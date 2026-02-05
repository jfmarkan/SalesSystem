<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $req)
    {
        $userId = (int) $req->query('user_id', Auth::id());

        try {
            // ---- unidad pedida (base VK-EH; soporta VKEH | M3 | EUR)
            $unit = strtoupper($req->query('unit', 'VKEH'));
            if (!in_array($unit, ['VKEH', 'M3', 'EUR'])) {
                $unit = 'VKEH';
            }

            // ==== GRANULARIDAD Y PERÍODO (MES ANCLA) =======================
            $rawPeriod = $req->query('period', Carbon::now()->format('Y-m'));
            try {
                $refMonth = Carbon::createFromFormat('Y-m', $rawPeriod)->startOfMonth();
            } catch (\Exception $e) {
                $refMonth = Carbon::now()->startOfMonth();
                $rawPeriod = $refMonth->format('Y-m');
            }

            // month|quarter|semester|year
            $granularity = strtolower($req->query('granularity', 'year'));
            if (!in_array($granularity, ['month', 'quarter', 'semester', 'year'])) {
                $granularity = 'year';
            }

            // Año fiscal: empieza en abril
            $fyStartYear = $refMonth->month >= 4 ? $refMonth->year : $refMonth->year - 1;
            $fyStart = Carbon::create($fyStartYear, 4, 1)->startOfMonth();
            $fyEnd   = Carbon::create($fyStartYear + 1, 3, 1)->endOfMonth();

            // Rango según granularidad (SIEMPRE sobre año fiscal)
            switch ($granularity) {
                case 'month':
                    // solo el mes seleccionado
                    $rangeStart = (clone $refMonth)->startOfMonth();
                    $rangeEnd   = (clone $refMonth)->endOfMonth();
                    break;

                case 'quarter':
                    // Quartale basados en FY:
                    // Q1: Apr-Jun, Q2: Jul-Sep, Q3: Okt-Dez, Q4: Jan-Mär
                    $m = $refMonth->month;
                    $y = $refMonth->year;
                    if ($m >= 4 && $m <= 6) {
                        $rangeStart = Carbon::create($y, 4, 1);
                        $rangeEnd   = Carbon::create($y, 6, 1)->endOfMonth();
                    } elseif ($m >= 7 && $m <= 9) {
                        $rangeStart = Carbon::create($y, 7, 1);
                        $rangeEnd   = Carbon::create($y, 9, 1)->endOfMonth();
                    } elseif ($m >= 10 && $m <= 12) {
                        $rangeStart = Carbon::create($y, 10, 1);
                        $rangeEnd   = Carbon::create($y, 12, 1)->endOfMonth();
                    } else { // 1..3 => Q4 des Fiskaljahres
                        $rangeStart = Carbon::create($y, 1, 1);
                        $rangeEnd   = Carbon::create($y, 3, 1)->endOfMonth();
                    }
                    break;

                case 'semester':
                    // 1. Halbjahr: Apr-Sep, 2. Halbjahr: Okt-Mär
                    $m = $refMonth->month;
                    $y = $refMonth->year;
                    if ($m >= 4 && $m <= 9) {
                        // 1. Halbjahr des laufenden Fiskaljahres
                        $rangeStart = Carbon::create($y, 4, 1);
                        $rangeEnd   = Carbon::create($y, 9, 1)->endOfMonth();
                    } else {
                        // 2. Halbjahr: Okt (Jahr X) – Mär (Jahr X+1)
                        if ($m >= 10) {
                            $rangeStart = Carbon::create($y, 10, 1);
                            $rangeEnd   = Carbon::create($y + 1, 3, 1)->endOfMonth();
                        } else { // 1..3
                            $rangeStart = Carbon::create($y - 1, 10, 1);
                            $rangeEnd   = Carbon::create($y, 3, 1)->endOfMonth();
                        }
                    }
                    break;

                case 'year':
                default:
                    // Geschäftsjahr komplett (Apr..Mär)
                    $rangeStart = $fyStart;
                    $rangeEnd   = $fyEnd;
                    break;
            }

            // YM enteros para filtro (yyyyMM)
            $ym = fn(Carbon $d) => $d->year * 100 + $d->month;
            $startYM = $ym($rangeStart);
            $endYM   = $ym($rangeEnd);

            // ---- tablas requeridas
            $hasAssignments = Schema::hasTable('assignments');
            $hasCPC         = Schema::hasTable('client_profit_centers');
            $hasPC          = Schema::hasTable('profit_centers');
            $hasSales       = Schema::hasTable('sales');
            $hasForecasts   = Schema::hasTable('forecasts');
            $hasBudgets     = Schema::hasTable('budgets');
            $hasConversions = Schema::hasTable('unit_conversions');
            $hasAI          = Schema::hasTable('action_items');
            $hasAP          = Schema::hasTable('action_plans');

            if (!$hasAssignments || !$hasCPC) {
                return response()->json([
                    'period'      => $rawPeriod,
                    'granularity' => $granularity,
                    'unit'        => $unit,
                    'range'       => [
                        'from' => $rangeStart->toDateString(),
                        'to'   => $rangeEnd->toDateString(),
                    ],
                    'kpis'    => ['items' => [], 'period' => $rawPeriod],
                    'chart'   => [
                        'labels' => [],
                        'codes'  => [],
                        'series' => [
                            ['name' => 'Ist',       'data' => []],
                            ['name' => 'Prognose',  'data' => []],
                            ['name' => 'Budget',    'data' => []],
                        ],
                        'unit' => $unit,
                    ],
                    'table'   => [
                        'rows'   => [],
                        'totals' => [
                            'ist'      => 0,
                            'prognose' => 0,
                            'budget'   => 0,
                            'unit'     => $unit,
                        ],
                        'unit'   => $unit,
                    ],
                    'calendar'=> $this->buildCalendar(),
                    'extra'   => [
                        'label'  => 'Zusatzquote',
                        'period' => $rawPeriod,
                        'total'  => 0,
                        'unit'   => 'M3',
                        'by_pc'  => [],
                    ],
                ]);
            }

            // ---- Profit centers asignados al usuario
            $pcNameCol = null;
            if ($hasPC) {
                if (Schema::hasColumn('profit_centers', 'profit_center_name')) {
                    $pcNameCol = 'profit_center_name';
                }
                if (Schema::hasColumn('profit_centers', 'prfit_center_name')) {
                    $pcNameCol = $pcNameCol ?: 'prfit_center_name';
                }
            }

            $assigned = DB::table('assignments AS a')
                ->join('client_profit_centers AS cpc', 'cpc.id', '=', 'a.client_profit_center_id')
                ->leftJoin('profit_centers AS pc', 'pc.profit_center_code', '=', 'cpc.profit_center_code')
                ->where('a.user_id', $userId)
                ->select(
                    'cpc.id AS cpc_id',
                    'cpc.profit_center_code AS pc_code',
                    DB::raw($pcNameCol ? "pc.$pcNameCol AS pc_name" : "cpc.profit_center_code AS pc_name")
                )
                ->distinct()
                ->get();

            $cpcIds = $assigned->pluck('cpc_id')->all();
            $pcMeta = [];
            foreach ($assigned as $row) {
                $pcMeta[$row->pc_code] = [
                    'code' => (string) $row->pc_code,
                    'name' => (string) $row->pc_name,
                ];
            }

            if (empty($cpcIds)) {
                $calendar = $this->buildCalendar();
                return response()->json([
                    'period'      => $rawPeriod,
                    'granularity' => $granularity,
                    'unit'        => $unit,
                    'range'       => [
                        'from' => $rangeStart->toDateString(),
                        'to'   => $rangeEnd->toDateString(),
                    ],
                    'kpis'    => ['items' => [], 'period' => $rawPeriod],
                    'chart'   => [
                        'labels' => [],
                        'codes'  => [],
                        'series' => [
                            ['name' => 'Ist',       'data' => []],
                            ['name' => 'Prognose',  'data' => []],
                            ['name' => 'Budget',    'data' => []],
                        ],
                        'unit' => $unit,
                    ],
                    'table'   => [
                        'rows'   => [],
                        'totals' => [
                            'ist'      => 0,
                            'prognose' => 0,
                            'budget'   => 0,
                            'unit'     => $unit,
                        ],
                        'unit'   => $unit,
                    ],
                    'calendar'=> $calendar,
                    'extra'   => [
                        'label'  => 'Zusatzquote',
                        'period' => $rawPeriod,
                        'total'  => 0,
                        'unit'   => 'M3',
                        'by_pc'  => [],
                    ],
                ]);
            }

            // ---- helper para comparar (yyyyMM)
            $ymRaw = fn($alias) => DB::raw("({$alias}.fiscal_year*100 + {$alias}.month)");

            /* ============================================================
             *  VENTAS
             * ============================================================ */
            $sumSalesBase = []; // VK-EH “base” por PC (units o m³ según PC)
            $sumSalesM3   = []; // siempre m³ reales de la tabla
            $sumSalesEUR  = []; // siempre € reales de la tabla

            if ($hasSales) {
                $rows = DB::table('sales AS s')
                    ->join('client_profit_centers AS cpc', 'cpc.id', '=', 's.client_profit_center_id')
                    ->whereIn('cpc.id', $cpcIds)
                    ->where($ymRaw('s'), '>=', $startYM)
                    ->where($ymRaw('s'), '<=', $endYM)
                    ->groupBy('cpc.profit_center_code')
                    ->select(
                        'cpc.profit_center_code AS pc_code',
                        DB::raw('COALESCE(SUM(s.sales_units),0)  AS units'),
                        DB::raw('COALESCE(SUM(s.cubic_meters),0) AS m3'),
                        DB::raw('COALESCE(SUM(s.euros),0)       AS eur')
                    )
                    ->get();

                foreach ($rows as $r) {
                    $code  = $r->pc_code;
                    $units = (float) $r->units;
                    $m3    = (float) $r->m3;
                    $eur   = (float) $r->eur;

                    $sumSalesM3[$code]  = $m3;
                    $sumSalesEUR[$code] = $eur;

                    // PCs especiales: su “VK-EH” lo queremos en m³
                    $isSpecialM3Base = in_array((int) $code, [110, 170, 171, 175], true);
                    $sumSalesBase[$code] = $isSpecialM3Base ? $m3 : $units;
                }
            }

            /* ============================================================
             *  FORECAST & BUDGET
             * ============================================================ */
            $sumForecast = [];
            if ($hasForecasts) {
                // última versión por (cpc, year, month)
                $latest = DB::table('forecasts')
                    ->select('client_profit_center_id', 'fiscal_year', 'month', DB::raw('MAX(version) AS mv'))
                    ->groupBy('client_profit_center_id', 'fiscal_year', 'month');

                $sumForecast = DB::table('forecasts AS f')
                    ->joinSub($latest, 'fm', function ($j) {
                        $j->on('fm.client_profit_center_id', '=', 'f.client_profit_center_id')
                          ->on('fm.fiscal_year', '=', 'f.fiscal_year')
                          ->on('fm.month', '=', 'f.month')
                          ->on('fm.mv', '=', 'f.version');
                    })
                    ->join('client_profit_centers AS cpc', 'cpc.id', '=', 'f.client_profit_center_id')
                    ->whereIn('cpc.id', $cpcIds)
                    ->where($ymRaw('f'), '>=', $startYM)
                    ->where($ymRaw('f'), '<=', $endYM)
                    ->groupBy('cpc.profit_center_code')
                    ->select('cpc.profit_center_code AS pc_code', DB::raw('SUM(f.volume) AS vkeh'))
                    ->pluck('vkeh', 'pc_code')->map(fn($v) => (float) $v)->toArray();
            }

            $sumBudget = [];
            if ($hasBudgets) {
                $sumBudget = DB::table('budgets AS b')
                    ->join('client_profit_centers AS cpc', 'cpc.id', '=', 'b.client_profit_center_id')
                    ->whereIn('cpc.id', $cpcIds)
                    ->where($ymRaw('b'), '>=', $startYM)
                    ->where($ymRaw('b'), '<=', $endYM)
                    ->groupBy('cpc.profit_center_code')
                    ->select('cpc.profit_center_code AS pc_code', DB::raw('SUM(b.volume) AS vkeh'))
                    ->pluck('vkeh', 'pc_code')->map(fn($v) => (float) $v)->toArray();
            }

            // ---- PCs con datos
            $pcCodes = collect(array_unique(array_merge(
                array_keys($sumSalesBase),
                array_keys($sumForecast),
                array_keys($sumBudget)
            )))->filter(function ($code) use ($sumSalesBase, $sumForecast, $sumBudget) {
                $sv = $sumSalesBase[$code]  ?? 0;
                $fv = $sumForecast[$code]   ?? 0;
                $bv = $sumBudget[$code]     ?? 0;
                return ($sv + $fv + $bv) > 0;
            })->values()->all();

            // Metadatos de PC
            $labels   = [];
            $codesArr = [];
            foreach ($pcCodes as $code) {
                $codesArr[] = (string) $code;
                $labels[]   = isset($pcMeta[$code]) ? (string) $pcMeta[$code]['name'] : (string) $code;
            }

            // ---- conversiones por PC
            $conv = []; // [pc => ['from_unit'=>, 'to_m3'=>, 'm3_to_eur'=>]]
            if ($hasConversions && !empty($pcCodes)) {
                $rows = DB::table('unit_conversions')
                    ->whereIn('profit_center_code', $pcCodes)
                    ->select('profit_center_code', 'from_unit', 'factor_to_m3', 'factor_to_euro')
                    ->get();
                foreach ($rows as $r) {
                    $conv[$r->profit_center_code] = [
                        'from_unit'  => strtoupper((string) ($r->from_unit ?? 'VKEH')),
                        'to_m3'      => (float) ($r->factor_to_m3   ?? 1),
                        'm3_to_eur'  => (float) ($r->factor_to_euro ?? 1),
                    ];
                }
            }

            // helper: convertir Budget/Forecast desde su unidad base a la unidad pedida
            $convertBF = function (string $pc, float $baseVal, string $u) use ($conv): float {
                if ($u === 'VKEH') return $baseVal;

                $c = $conv[$pc] ?? ['from_unit' => 'VKEH', 'to_m3' => 1, 'm3_to_eur' => 1];
                $from = strtoupper($c['from_unit'] ?? 'VKEH');

                // si la unidad base es ya m³, no multiplicamos por factor_to_m3
                if ($from === 'M3') {
                    $m3 = $baseVal;
                } else {
                    $m3 = $baseVal * ($c['to_m3'] ?? 1);
                }

                if ($u === 'M3') return $m3;
                // EUR
                return $m3 * ($c['m3_to_eur'] ?? 1);
            };

            // helper: obtener ventas en la unidad pedida
            $getSales = function (string $pc, string $u) use ($sumSalesBase, $sumSalesM3, $sumSalesEUR): float {
                if ($u === 'VKEH') return (float) ($sumSalesBase[$pc] ?? 0.0);
                if ($u === 'M3')   return (float) ($sumSalesM3[$pc]   ?? 0.0);
                if ($u === 'EUR')  return (float) ($sumSalesEUR[$pc]  ?? 0.0);
                return 0.0;
            };

            // ---- chart series en unidad pedida
            $seriesIst      = [];
            $seriesForecast = [];
            $seriesBudget   = [];
            foreach ($pcCodes as $code) {
                $codeStr = (string) $code;

                $svU = $getSales($codeStr, $unit);
                $fvU = $convertBF($codeStr, (float) ($sumForecast[$codeStr] ?? 0), $unit);
                $bvU = $convertBF($codeStr, (float) ($sumBudget[$codeStr]   ?? 0), $unit);

                $seriesIst[]      = round($svU, 4);
                $seriesForecast[] = round($fvU, 4);
                $seriesBudget[]   = round($bvU, 4);
            }

            $chart = [
                'labels' => $labels,
                'codes'  => $codesArr,
                'series' => [
                    ['name' => 'Ist',       'data' => $seriesIst],
                    ['name' => 'Prognose',  'data' => $seriesForecast],
                    ['name' => 'Budget',    'data' => $seriesBudget],
                ],
                'unit'   => $unit,
            ];

            // ---- tabla
            $rows = [];
            $totIst = 0.0;
            $totF   = 0.0;
            $totB   = 0.0;

            foreach ($pcCodes as $code) {
                $codeStr = (string) $code;

                $svU = $getSales($codeStr, $unit);
                $fvU = $convertBF($codeStr, (float) ($sumForecast[$codeStr] ?? 0), $unit);
                $bvU = $convertBF($codeStr, (float) ($sumBudget[$codeStr]   ?? 0), $unit);

                $totIst += $svU;
                $totF   += $fvU;
                $totB   += $bvU;

                $rows[] = [
                    'pc_code'   => $codeStr,
                    'pc_name'   => (string) ($pcMeta[$code]['name'] ?? $codeStr),
                    'ist'       => round($svU, 4),
                    'prognose'  => round($fvU, 4),
                    'budget'    => round($bvU, 4),
                ];
            }

            $table = [
                'rows'   => $rows,
                'totals' => [
                    'ist'      => round($totIst, 4),
                    'prognose' => round($totF, 4),
                    'budget'   => round($totB, 4),
                    'unit'     => $unit,
                ],
                'unit'   => $unit,
            ];

            // ---- KPIs (acumulados EN EL RANGO SELECCIONADO) ==============

            // Base VK-EH para proporciones (ventas base, forecast base, budget base)
            $sumVK = function (string $field) use ($pcCodes, $sumSalesBase, $sumForecast, $sumBudget): float {
                $s = 0.0;
                foreach ($pcCodes as $code) {
                    if ($field === 'sales')    $s += (float) ($sumSalesBase[$code] ?? 0);
                    if ($field === 'forecast') $s += (float) ($sumForecast[$code]  ?? 0);
                    if ($field === 'budget')   $s += (float) ($sumBudget[$code]    ?? 0);
                }
                return $s;
            };

            $salesVK    = $sumVK('sales');
            $forecastVK = $sumVK('forecast');
            $budgetVK   = $sumVK('budget');

            // En m³ y EUR:
            $sumM3 = function (array $vector) use ($pcCodes, $conv): float {
                $t = 0.0;
                foreach ($pcCodes as $code) {
                    $val = (float) ($vector[$code] ?? 0.0);
                    $c   = $conv[$code] ?? ['from_unit' => 'VKEH', 'to_m3' => 1];
                    $from = strtoupper($c['from_unit'] ?? 'VKEH');
                    if ($from === 'M3') {
                        $m3 = $val;
                    } else {
                        $m3 = $val * ($c['to_m3'] ?? 1);
                    }
                    $t += $m3;
                }
                return $t;
            };

            $sumEUR = function (array $vector) use ($pcCodes, $conv): float {
                $t = 0.0;
                foreach ($pcCodes as $code) {
                    $val = (float) ($vector[$code] ?? 0.0);
                    $c   = $conv[$code] ?? ['from_unit' => 'VKEH', 'to_m3' => 1, 'm3_to_eur' => 1];
                    $from = strtoupper($c['from_unit'] ?? 'VKEH');
                    if ($from === 'M3') {
                        $m3 = $val;
                    } else {
                        $m3 = $val * ($c['to_m3'] ?? 1);
                    }
                    $eur = $m3 * ($c['m3_to_eur'] ?? 1);
                    $t  += $eur;
                }
                return $t;
            };

            // Ventas: usamos directamente los agregados de la tabla sales
            $salesM3  = array_sum($sumSalesM3);
            $salesEUR = array_sum($sumSalesEUR);

            // Forecast/Budget: conversión desde volume
            $forecastM3 = $sumM3($sumForecast);
            $budgetM3   = $sumM3($sumBudget);

            // ratios (Ist vs Prognose, Ist vs Budget): (Ist/Target - 1) * 100
            $ratio = function (float $a, float $b): float {
                if (abs($b) < 1e-9) return 0.0;
                return (($a / max($b, 1e-9)) - 1.0) * 100.0;
            };

            // ===== Deviations KPIs =======================================
            $devStats = [
                'total'        => 0,
                'resolved'     => 0,
                'open'         => 0,
                'budget_cases' => 0,
            ];

            if (Schema::hasTable('deviations')) {
                $devQ = DB::table('deviations')->where('user_id', $userId);

                // si existen columnas fiscal_year/month, filtramos por el mismo rango
                if (Schema::hasColumn('deviations', 'fiscal_year') && Schema::hasColumn('deviations', 'month')) {
                    $devQ->whereBetween(DB::raw('fiscal_year*100 + month'), [$startYM, $endYM]);
                } elseif (Schema::hasColumn('deviations', 'year') && Schema::hasColumn('deviations', 'month')) {
                    $devQ->whereBetween(DB::raw('year*100 + month'), [$startYM, $endYM]);
                }

                $baseDevQ = clone $devQ;

                // total desviaciones
                $devStats['total'] = (clone $baseDevQ)->count();

                // resueltas (según resolved_at o status)
                if (Schema::hasColumn('deviations', 'resolved_at')) {
                    $devStats['resolved'] = (clone $baseDevQ)
                        ->whereNotNull('resolved_at')
                        ->count();
                } elseif (Schema::hasColumn('deviations', 'status')) {
                    $devStats['resolved'] = (clone $baseDevQ)
                        ->whereIn('status', ['resolved', 'closed', 'done'])
                        ->count();
                }

                $devStats['open'] = max(0, $devStats['total'] - $devStats['resolved']);

                // ==== Budget Cases: vía client_profit_center_id + assignments ====
                if (Schema::hasTable('budget_cases')) {
                    $bcQ = DB::table('budget_cases AS bc');

                    // vínculo BC → assignments → user
                    if (
                        Schema::hasColumn('budget_cases', 'client_profit_center_id') &&
                        Schema::hasTable('assignments') &&
                        Schema::hasColumn('assignments', 'client_profit_center_id') &&
                        Schema::hasColumn('assignments', 'user_id')
                    ) {
                        $bcQ->join('assignments AS a', 'a.client_profit_center_id', '=', 'bc.client_profit_center_id')
                            ->where('a.user_id', $userId);
                    }

                    // Filtro por año fiscal si la tabla tiene fiscal_year + month
                    if (Schema::hasColumn('budget_cases', 'fiscal_year') && Schema::hasColumn('budget_cases', 'month')) {
                        $bcQ->whereBetween(DB::raw('bc.fiscal_year*100 + bc.month'), [$startYM, $endYM]);
                    }

                    // contamos budget_cases distintos (por si un mismo BC aparece en varios assignments)
                    $devStats['budget_cases'] = $bcQ->distinct('bc.id')->count('bc.id');
                }
            }

            $devResolvedPct = $devStats['total'] > 0
                ? round($devStats['resolved'] * 100.0 / max(1, $devStats['total']), 2)
                : 0.0;

            // ---- KPIs array (incluye los devStats)
            $kpis = [
                'items' => [
                    [
                        'id'    => 'ist_vs_prognose',
                        'label' => 'Ist vs Prognose',
                        'value' => round($ratio($salesVK, $forecastVK), 2),
                        'unit'  => '%',
                        'target'=> 0, // Ziel: 0% Abweichung
                        'details' => [
                            'ist_m3'      => round($salesM3, 2),
                            'prognose_m3' => round($forecastM3, 2),
                            'diff_m3'     => round($salesM3 - $forecastM3, 2),
                        ],
                    ],
                    [
                        'id'    => 'ist_vs_budget',
                        'label' => 'Ist vs Budget',
                        'value' => round($ratio($salesVK, $budgetVK), 2),
                        'unit'  => '%',
                        'target'=> 0, // Ziel: 0% Abweichung
                        'details' => [
                            'ist_m3'    => round($salesM3, 2),
                            'budget_m3' => round($budgetM3, 2),
                            'diff_m3'   => round($salesM3 - $budgetM3, 2),
                        ],
                    ],
                    [
                        'id'    => 'diff_ist_budget_m3',
                        'label' => 'Differenz Ist–Budget',
                        'value' => round($salesM3 - $budgetM3, 2),
                        'unit'  => 'M3',
                        'target'=> 0,
                    ],
                    [
                        'id'    => 'umsatz_eur',
                        'label' => 'Gesamtumsatz',
                        'value' => round($salesEUR, 2),
                        'unit'  => 'EUR',
                    ],
                    // Deviations KPIs extra
                    [
                        'id'    => 'deviations_total',
                        'label' => 'Abweichungen gesamt',
                        'value' => $devStats['total'],
                        'unit'  => 'Stk',
                        'details' => $devStats,
                    ],
                    [
                        'id'    => 'deviations_resolved_pct',
                        'label' => 'Abweichungen gelöst',
                        'value' => $devResolvedPct,
                        'unit'  => '%',
                        'target'=> 100, // Ziel: 100% gelöst
                        'details' => $devStats,
                    ],
                    [
                        'id'    => 'deviations_budget_cases',
                        'label' => 'Budget Cases',
                        'value' => $devStats['budget_cases'],
                        'unit'  => 'Stk',
                    ],
                ],
                'period'      => $rawPeriod,
                'granularity' => $granularity,
                'range'       => [
                    'from' => $rangeStart->toDateString(),
                    'to'   => $rangeEnd->toDateString(),
                ],
            ];

            // ---- calendario (mes actual → futuro)
            $calendar = $this->buildCalendar();

            // ---- extra (stub)
            $extra = [
                'label'  => 'Zusatzquote',
                'period' => $rawPeriod,
                'total'  => 0,
                'unit'   => 'M3',
                'by_pc'  => [],
            ];

            return response()->json([
                'period'      => $rawPeriod,
                'granularity' => $granularity,
                'unit'        => $unit,
                'range'       => [
                    'from' => $rangeStart->toDateString(),
                    'to'   => $rangeEnd->toDateString(),
                ],
                'kpis'    => $kpis,
                'chart'   => $chart,
                'table'   => $table,
                'calendar'=> $calendar,
                'extra'   => $extra,
            ]);
        } catch (\Throwable $e) {
            // Log MUCHO más descriptivo
            Log::error('Dashboard error', [
                'user_id'     => $userId,
                'unit'        => $unit ?? null,
                'period_param'=> $req->query('period'),
                'granularity_param' => $req->query('granularity'),
                'resolved_period'   => $rawPeriod ?? null,
                'message'     => $e->getMessage(),
                'exception'   => get_class($e),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message'   => 'Dashboard konnte nicht geladen werden.',
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
            ], 500);
        }
    }

    public function unitsByPc(Request $req)
    {
        $codes = $req->query('codes', '');
        if (is_string($codes)) {
            $codes = array_filter(array_map('trim', explode(',', $codes)), fn($c) => $c !== '');
        }
        if (!is_array($codes) || empty($codes)) {
            return response()->json([]);
        }

        if (!Schema::hasTable('unit_conversions')) {
            // fallback: todo VK-EH si no hay tabla
            $out = [];
            foreach ($codes as $c) {
                $out[(string) $c] = 'VKEH';
            }
            return response()->json($out);
        }

        $rows = DB::table('unit_conversions')
            ->whereIn('profit_center_code', $codes)
            ->select('profit_center_code', 'from_unit')
            ->get();

        $map = [];
        foreach ($codes as $c) {
            $map[(string) $c] = 'VKEH'; // default
        }
        foreach ($rows as $r) {
            $code = (string) $r->profit_center_code;
            $fu   = (string) ($r->from_unit ?? 'VKEH');
            $map[$code] = $fu ?: 'VKEH';
        }
        return response()->json($map);
    }

    /**
     * Build calendar payload from current month forward, for the logged-in user.
     * (Se usa en el dashboard como "resumen" – para el calendario visual usás CalendarController)
     */
    private function buildCalendar(): array
    {
        $events = collect();
        $fromCal = Carbon::now()->startOfMonth()->toDateString();

        if (Schema::hasTable('action_items') && Schema::hasTable('action_plans')) {
            $today = Carbon::today()->toDateString();

            $rows = DB::table('action_items AS ai')
                ->join('action_plans AS ap', 'ap.id', '=', 'ai.action_plan_id')
                ->select(
                    'ai.id',
                    'ai.title',
                    'ai.description',
                    'ai.due_date',
                    'ap.deviation_id',
                    'ap.user_id',
                    'ap.objective'
                )
                ->where('ap.user_id', Auth::id())
                ->whereDate('ai.due_date', '>=', $fromCal)   // mes actual y futuros
                ->orderBy('ai.due_date')
                ->get();

            $events = $rows->map(fn($r) => [
                'id'          => $r->id,
                'title'       => $r->title,
                'start'       => $r->due_date,
                'end'         => $r->due_date,
                'allDay'      => true,
                'status'      => $r->due_date < $today ? 'overdue' : 'pending',
                'extendedProps' => [
                    'description'   => $r->description,
                    'deviation_id'  => $r->deviation_id,
                    'objective'     => $r->objective,
                    'owner_user_id' => $r->user_id,
                ],
            ]);
        }

        return [
            'from'   => $fromCal,
            'to'     => null,
            'events' => $events,
        ];
    }
}
