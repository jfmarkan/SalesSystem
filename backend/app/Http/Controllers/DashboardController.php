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
            if (!in_array($unit, ['VKEH','M3','EUR'])) $unit = 'VKEH';

            // Period anchor (Y-m). El rango real es abril .. último mes completo relativo a "period"
            $period    = $req->query('period', Carbon::now()->format('Y-m'));
            $refMonth  = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            $lastComplete = (clone $refMonth)->subMonth()->endOfMonth();

            // Inicio fiscal en abril del año correspondiente
            if ($lastComplete->month >= 4) {
                $rangeStart = Carbon::create($lastComplete->year, 4, 1)->startOfMonth();
            } else {
                $rangeStart = Carbon::create($lastComplete->year - 1, 4, 1)->startOfMonth();
            }
            $rangeEnd = (clone $lastComplete)->endOfMonth();

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
                    'period'  => $period,
                    'unit'    => $unit,
                    'range'   => ['from'=>$rangeStart->toDateString(),'to'=>$rangeEnd->toDateString()],
                    'kpis'    => ['items'=>[], 'period'=>$period],
                    'chart'   => [
                        'labels'=>[],
                        'codes'=>[],
                        'series'=>[
                            ['name'=>'Ist','data'=>[]],
                            ['name'=>'Prognose','data'=>[]],
                            ['name'=>'Budget','data'=>[]],
                        ],
                        'unit'=>$unit
                    ],
                    'table'   => [
                        'rows'=>[],
                        'totals'=>['ist'=>0,'prognose'=>0,'budget'=>0,'unit'=>$unit],
                        'unit'=>$unit
                    ],
                    'calendar'=> ['from'=>Carbon::now()->startOfMonth()->toDateString(), 'to'=>null, 'events'=>collect()],
                    'extra'   => ['label'=>'Zusatzquote','period'=>$period,'total'=>0,'unit'=>'M3','by_pc'=>[]],
                ]);
            }

            // ---- Profit centers asignados al usuario
            $pcNameCol = null;
            if ($hasPC) {
                if (Schema::hasColumn('profit_centers','profit_center_name')) $pcNameCol = 'profit_center_name';
                if (Schema::hasColumn('profit_centers','prfit_center_name'))  $pcNameCol = $pcNameCol ?: 'prfit_center_name';
            }

            $assigned = DB::table('assignments AS a')
                ->join('client_profit_centers AS cpc','cpc.id','=','a.client_profit_center_id')
                ->leftJoin('profit_centers AS pc','pc.profit_center_code','=','cpc.profit_center_code')
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
                    'code' => (string)$row->pc_code,
                    'name' => (string)$row->pc_name,
                ];
            }

            if (empty($cpcIds)) {
                $calendar = $this->buildCalendar();
                return response()->json([
                    'period'  => $period,
                    'unit'    => $unit,
                    'range'   => ['from'=>$rangeStart->toDateString(),'to'=>$rangeEnd->toDateString()],
                    'kpis'    => ['items'=>[], 'period'=>$period],
                    'chart'   => [
                        'labels'=>[],
                        'codes'=>[],
                        'series'=>[
                            ['name'=>'Ist','data'=>[]],
                            ['name'=>'Prognose','data'=>[]],
                            ['name'=>'Budget','data'=>[]],
                        ],
                        'unit'=>$unit
                    ],
                    'table'   => [
                        'rows'=>[],
                        'totals'=>['ist'=>0,'prognose'=>0,'budget'=>0,'unit'=>$unit],
                        'unit'=>$unit
                    ],
                    'calendar'=> $calendar,
                    'extra'   => ['label'=>'Zusatzquote','period'=>$period,'total'=>0,'unit'=>'M3','by_pc'=>[]],
                ]);
            }

            // ---- helper para comparar (yyyyMM)
            $ymRaw = fn($alias) => DB::raw("({$alias}.fiscal_year*100 + {$alias}.month)");

            /* ============================================================
             *  VENTAS: usar columnas reales (sales_units, cubic_meters, euros)
             *  Sin factores. Para PCs 110/170/171/175 la “base” (VKEH) será m³.
             * ============================================================ */
            $sumSalesBase = []; // VK-EH “base” por PC (units o m³ según PC)
            $sumSalesM3   = []; // siempre m³ reales de la tabla
            $sumSalesEUR  = []; // siempre € reales de la tabla

            if ($hasSales) {
                $rows = DB::table('sales AS s')
                    ->join('client_profit_centers AS cpc','cpc.id','=','s.client_profit_center_id')
                    ->whereIn('cpc.id', $cpcIds)
                    ->where($ymRaw('s'), '>=', $startYM)
                    ->where($ymRaw('s'), '<=', $endYM)
                    ->groupBy('cpc.profit_center_code')
                    ->select(
                        'cpc.profit_center_code AS pc_code',
                        DB::raw('COALESCE(SUM(s.sales_units),0)     AS units'),
                        DB::raw('COALESCE(SUM(s.cubic_meters),0)    AS m3'),
                        DB::raw('COALESCE(SUM(s.euros),0)           AS eur')
                    )
                    ->get();

                foreach ($rows as $r) {
                    $code  = $r->pc_code;
                    $units = (float)$r->units;
                    $m3    = (float)$r->m3;
                    $eur   = (float)$r->eur;

                    $sumSalesM3[$code]  = $m3;
                    $sumSalesEUR[$code] = $eur;

                    // PCs especiales: su “VK-EH” lo queremos en m³
                    $isSpecialM3Base = in_array((int)$code, [110,170,171,175], true);
                    $sumSalesBase[$code] = $isSpecialM3Base ? $m3 : $units;
                }
            }

            /* ============================================================
             *  FORECAST & BUDGET: siguen en columna `volume` (VK-EH base)
             *  Para m³ / € seguimos usando unit_conversions.
             * ============================================================ */
            $sumForecast = [];
            if ($hasForecasts) {
                // última versión por (cpc, year, month)
                $latest = DB::table('forecasts')
                    ->select('client_profit_center_id','fiscal_year','month', DB::raw('MAX(version) AS mv'))
                    ->groupBy('client_profit_center_id','fiscal_year','month');

                $sumForecast = DB::table('forecasts AS f')
                    ->joinSub($latest, 'fm', function($j){
                        $j->on('fm.client_profit_center_id','=','f.client_profit_center_id')
                          ->on('fm.fiscal_year','=','f.fiscal_year')
                          ->on('fm.month','=','f.month')
                          ->on('fm.mv','=','f.version');
                    })
                    ->join('client_profit_centers AS cpc','cpc.id','=','f.client_profit_center_id')
                    ->whereIn('cpc.id', $cpcIds)
                    ->where($ymRaw('f'), '>=', $startYM)
                    ->where($ymRaw('f'), '<=', $endYM)
                    ->groupBy('cpc.profit_center_code')
                    ->select('cpc.profit_center_code AS pc_code', DB::raw('SUM(f.volume) AS vkeh'))
                    ->pluck('vkeh','pc_code')->map(fn($v)=> (float)$v)->toArray();
            }

            $sumBudget = [];
            if ($hasBudgets) {
                $sumBudget = DB::table('budgets AS b')
                    ->join('client_profit_centers AS cpc','cpc.id','=','b.client_profit_center_id')
                    ->whereIn('cpc.id', $cpcIds)
                    ->where($ymRaw('b'), '>=', $startYM)
                    ->where($ymRaw('b'), '<=', $endYM)
                    ->groupBy('cpc.profit_center_code')
                    ->select('cpc.profit_center_code AS pc_code', DB::raw('SUM(b.volume) AS vkeh'))
                    ->pluck('vkeh','pc_code')->map(fn($v)=> (float)$v)->toArray();
            }

            // ---- PCs con datos (union de claves con alguna suma >0)
            $pcCodes = collect(array_unique(array_merge(
                array_keys($sumSalesBase),
                array_keys($sumForecast),
                array_keys($sumBudget)
            )))->filter(function($code) use ($sumSalesBase,$sumForecast,$sumBudget){
                $sv = $sumSalesBase[$code]  ?? 0;
                $fv = $sumForecast[$code]   ?? 0;
                $bv = $sumBudget[$code]     ?? 0;
                return ($sv + $fv + $bv) > 0;
            })->values()->all();

            // Metadatos de PC
            $labels  = [];
            $codesArr= [];
            foreach ($pcCodes as $code) {
                $codesArr[] = (string)$code;
                $labels[]   = isset($pcMeta[$code]) ? (string)$pcMeta[$code]['name'] : (string)$code;
            }

            // ---- conversiones por PC (solo para Budget/Forecast)
            $conv = []; // [pc => ['from_unit'=>, 'to_m3'=>, 'm3_to_eur'=>]]
            if ($hasConversions && !empty($pcCodes)) {
                $rows = DB::table('unit_conversions')
                    ->whereIn('profit_center_code', $pcCodes)
                    ->select('profit_center_code','from_unit','factor_to_m3','factor_to_euro')
                    ->get();
                foreach ($rows as $r) {
                    $conv[$r->profit_center_code] = [
                        'from_unit'  => strtoupper((string)($r->from_unit ?? 'VKEH')),
                        'to_m3'      => (float)($r->factor_to_m3   ?? 1),
                        'm3_to_eur'  => (float)($r->factor_to_euro ?? 1),
                    ];
                }
            }

            // helper: convertir Budget/Forecast desde su unidad base a la unidad pedida
            $convertBF = function(string $pc, float $baseVal, string $u) use ($conv): float {
                if ($u === 'VKEH') return $baseVal;

                $c = $conv[$pc] ?? ['from_unit'=>'VKEH','to_m3'=>1,'m3_to_eur'=>1];
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
            $getSales = function(string $pc, string $u) use ($sumSalesBase,$sumSalesM3,$sumSalesEUR): float {
                if ($u === 'VKEH') return (float)($sumSalesBase[$pc] ?? 0.0);
                if ($u === 'M3')   return (float)($sumSalesM3[$pc]   ?? 0.0);
                if ($u === 'EUR')  return (float)($sumSalesEUR[$pc]  ?? 0.0);
                return 0.0;
            };

            // ---- chart series en unidad pedida
            $seriesIst      = [];
            $seriesForecast = [];
            $seriesBudget   = [];
            foreach ($pcCodes as $code) {
                $codeStr = (string)$code;

                $svU = $getSales($codeStr, $unit);
                $fvU = $convertBF($codeStr, (float)($sumForecast[$codeStr] ?? 0), $unit);
                $bvU = $convertBF($codeStr, (float)($sumBudget[$codeStr]   ?? 0), $unit);

                $seriesIst[]      = round($svU, 4);
                $seriesForecast[] = round($fvU, 4);
                $seriesBudget[]   = round($bvU, 4);
            }
            $chart = [
                'labels' => $labels,
                'codes'  => $codesArr,
                'series' => [
                    ['name'=>'Ist','data'=>$seriesIst],
                    ['name'=>'Prognose','data'=>$seriesForecast],
                    ['name'=>'Budget','data'=>$seriesBudget],
                ],
                'unit'   => $unit,
            ];

            // ---- tabla
            $rows = [];
            $totIst=0.0; $totF=0.0; $totB=0.0;
            foreach ($pcCodes as $code) {
                $codeStr = (string)$code;

                $svU = $getSales($codeStr, $unit);
                $fvU = $convertBF($codeStr, (float)($sumForecast[$codeStr] ?? 0), $unit);
                $bvU = $convertBF($codeStr, (float)($sumBudget[$codeStr]   ?? 0), $unit);

                $totIst += $svU;
                $totF   += $fvU;
                $totB   += $bvU;

                $rows[] = [
                    'pc_code'   => $codeStr,
                    'pc_name'   => (string)($pcMeta[$code]['name'] ?? $codeStr),
                    'ist'       => round($svU, 4),
                    'prognose'  => round($fvU, 4),
                    'budget'    => round($bvU, 4),
                ];
            }
            $table = [
                'rows'   => $rows,
                'totals' => [
                    'ist'      => round($totIst,4),
                    'prognose' => round($totF,4),
                    'budget'   => round($totB,4),
                    'unit'     => $unit,
                ],
                'unit'   => $unit,
            ];

            // ---- KPIs (acumulados abril..último mes completo)

            // Base VK-EH para proporciones (ventas base, forecast base, budget base)
            $sumVK = function(string $field) use ($pcCodes,$sumSalesBase,$sumForecast,$sumBudget): float {
                $s=0.0;
                foreach ($pcCodes as $code) {
                    if ($field==='sales')    $s += (float)($sumSalesBase[$code] ?? 0);
                    if ($field==='forecast') $s += (float)($sumForecast[$code]  ?? 0);
                    if ($field==='budget')   $s += (float)($sumBudget[$code]    ?? 0);
                }
                return $s;
            };
            $salesVK    = $sumVK('sales');
            $forecastVK = $sumVK('forecast');
            $budgetVK   = $sumVK('budget');

            // En m³ y EUR:
            //  - ventas usan directamente las columnas de `sales`
            //  - budget/forecast se convierten vía unit_conversions
            $sumM3 = function(array $vector) use ($pcCodes,$conv): float {
                $t=0.0;
                foreach ($pcCodes as $code) {
                    $val = (float)($vector[$code] ?? 0.0);
                    $c   = $conv[$code] ?? ['from_unit'=>'VKEH','to_m3'=>1];
                    $from= strtoupper($c['from_unit'] ?? 'VKEH');
                    if ($from === 'M3') {
                        $m3 = $val;
                    } else {
                        $m3 = $val * ($c['to_m3'] ?? 1);
                    }
                    $t += $m3;
                }
                return $t;
            };
            $sumEUR = function(array $vector) use ($pcCodes,$conv): float {
                $t=0.0;
                foreach ($pcCodes as $code) {
                    $val = (float)($vector[$code] ?? 0.0);
                    $c   = $conv[$code] ?? ['from_unit'=>'VKEH','to_m3'=>1,'m3_to_eur'=>1];
                    $from= strtoupper($c['from_unit'] ?? 'VKEH');
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
            $salesM3    = array_sum($sumSalesM3);
            $salesEUR   = array_sum($sumSalesEUR);

            // Forecast/Budget: conversión desde volume
            $forecastM3 = $sumM3($sumForecast);
            $budgetM3   = $sumM3($sumBudget);

            // ratios (Ist vs Prognose, Ist vs Budget): (Ist/Target - 1) * 100
            $ratio = function(float $a, float $b): float {
                if (abs($b) < 1e-9) return 0.0;
                return (($a / max($b,1e-9)) - 1.0) * 100.0;
            };

            $kpis = [
                'items' => [
                    [
                        'id'    => 'ist_vs_prognose',
                        'label' => 'Ist vs Prognose',
                        'value' => round($ratio($salesVK, $forecastVK), 2),
                        'unit'  => '%',
                        'details' => [
                            'ist_m3'      => round($salesM3,2),
                            'prognose_m3' => round($forecastM3,2),
                            'diff_m3'     => round($salesM3 - $forecastM3,2),
                        ],
                    ],
                    [
                        'id'    => 'ist_vs_budget',
                        'label' => 'Ist vs Budget',
                        'value' => round($ratio($salesVK, $budgetVK), 2),
                        'unit'  => '%',
                        'details' => [
                            'ist_m3'    => round($salesM3,2),
                            'budget_m3' => round($budgetM3,2),
                            'diff_m3'   => round($salesM3 - $budgetM3,2),
                        ],
                    ],
                    [
                        'id'    => 'diff_ist_budget_m3',
                        'label' => 'Differenz Ist–Budget',
                        'value' => round($salesM3 - $budgetM3, 2),
                        'unit'  => 'M3',
                    ],
                    [
                        'id'    => 'umsatz_eur',
                        'label' => 'Gesamtumsatz',
                        'value' => round($salesEUR, 2),
                        'unit'  => 'EUR',
                    ],
                ],
                'period' => $period,
                'range'  => ['from'=>$rangeStart->toDateString(),'to'=>$rangeEnd->toDateString()],
            ];

            // ---- calendario (mes actual → futuro)
            $calendar = $this->buildCalendar();

            // ---- extra (stub)
            $extra = [
                'label'  => 'Zusatzquote',
                'period' => $period,
                'total'  => 0,
                'unit'   => 'M3',
                'by_pc'  => [],
            ];

            return response()->json([
                'period'  => $period,
                'unit'    => $unit,
                'range'   => ['from'=>$rangeStart->toDateString(),'to'=>$rangeEnd->toDateString()],
                'kpis'    => $kpis,
                'chart'   => $chart,
                'table'   => $table,
                'calendar'=> $calendar,
                'extra'   => $extra,
            ]);
        } catch (\Throwable $e) {
            Log::error('Dashboard error', [
                'error'=>$e->getMessage(),
                'trace'=>$e->getTraceAsString()
            ]);
            return response()->json(['message'=>'Server error'], 500);
        }
    }

    public function unitsByPc(Request $req)
    {
        $codes = $req->query('codes', '');
        if (is_string($codes)) {
            $codes = array_filter(array_map('trim', explode(',', $codes)), fn($c)=>$c!=='');
        }
        if (!is_array($codes) || empty($codes)) {
            return response()->json([]);
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('unit_conversions')) {
            // fallback: todo VK-EH si no hay tabla
            $out = [];
            foreach ($codes as $c) $out[(string)$c] = 'VKEH';
            return response()->json($out);
        }

        $rows = \Illuminate\Support\Facades\DB::table('unit_conversions')
            ->whereIn('profit_center_code', $codes)
            ->select('profit_center_code','from_unit')
            ->get();

        $map = [];
        foreach ($codes as $c) $map[(string)$c] = 'VKEH'; // default
        foreach ($rows as $r) {
            $code = (string)$r->profit_center_code;
            $fu   = (string)($r->from_unit ?? 'VKEH');
            $map[$code] = $fu ?: 'VKEH';
        }
        return response()->json($map);
    }

    /**
     * Build calendar payload from current month forward, for the logged-in user.
     */
    private function buildCalendar(): array
    {
        $events = collect();
        $fromCal = Carbon::now()->startOfMonth()->toDateString();

        if (Schema::hasTable('action_items') && Schema::hasTable('action_plans')) {
            $today = Carbon::today()->toDateString();

            $rows = DB::table('action_items AS ai')
                ->join('action_plans AS ap','ap.id','=','ai.action_plan_id')
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

