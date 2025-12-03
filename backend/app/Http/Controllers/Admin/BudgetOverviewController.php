<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BudgetOverviewController extends Controller
{
    /**
     * Vista general de Budgetierung:
     * - Sólo clientes A, B, PA, PB (classification_id 1,2,6,7) para vendedores
     * - C, D, X se usan para el Basisvolumen global y por Profit Center
     * - Basisvolumen en m³:
     *      · Por vendedor/PC/cliente: ventas YTD calendario (sales.cubic_meters)
     *        anualizadas por seasonality
     *      · Global: usa TODAS las ventas del año base (todas las clasificaciones),
     *        anualizadas (computeGlobalBasisM3)
     * - Best/Worst a partir de budget_cases (sólo A/B/PA/PB por CPC)
     *   ⚠️ si budget_cases.skip_budget = 1 → Best/Worst m³ = 0 (no se planea nada)
     * - Vorjahr = budget completo del año fiscal anterior en m³ (budgets.volume * factor_to_m3)
     * - Cobertura por clasificación y global
     * - Totales por vendedor + detalle por PC + clientes
     * - Lista de CPC sin budget case (pendientes)
     * - by_pc: resumen global por Profit Center + mix por Kundentyp
     *
     * GET /api/budget-cases/overview
     *   ?target_fiscal_year=2026 (opcional, por defecto = FY siguiente al actual)
     */
    public function index(Request $request)
    {
        // === 1) Determinar año objetivo y base de ventas (Basis) ===
        $now = Carbon::now();

        // FY actual (Abr–Mar)
        $currentFy = $this->fiscalYearFromDate($now);

        // Budget cases siempre son para el FY siguiente
        $defaultTargetFy = $currentFy + 1;

        $targetFy = (int) $request->query('target_fiscal_year', $defaultTargetFy);
        if ($targetFy < 2024) {
            $targetFy = 2024;
        }

        // Año calendario base = año anterior al FY objetivo
        // Si targetFy=2026 → base calendar year = 2025
        $baseCalendarYear = $targetFy - 1;

        // Último mes completo del año base
        if ($baseCalendarYear === (int) $now->year) {
            $capMonth = max(1, min(12, $now->month - 1));
        } elseif ($baseCalendarYear < (int) $now->year) {
            $capMonth = 12;
        } else {
            $capMonth = 0;
        }

        // Año fiscal previo para Vorjahr
        $prevFyStart = $targetFy - 1;

        // 🔹 Basisvolumen GLOBAL (usa TODAS las ventas del año calendario base anualizadas)
        $globalBasisM3 = $this->computeGlobalBasisM3($baseCalendarYear, $capMonth);

        // 🔹 Basisvolumen por Profit Center + Kundentyp (A,B,C,D,PA,PB,X)
        $classBasis = $this->loadClassBasisByPcAndClass($baseCalendarYear, $capMonth);
        $basisByPc    = $classBasis['by_pc'];    // [pcCode => [classKey => ['base_m3', 'count_cpc']]]
        $basisByClass = $classBasis['by_class']; // [classKey => base_m3]

        // === 2) Traer todos los CPC asignados a vendedores para A/B/PA/PB ===
        $classIdsForSellers = [1, 2, 6, 7];

        $rows = DB::table('assignments')
            ->join('client_profit_centers', 'client_profit_centers.id', '=', 'assignments.client_profit_center_id')
            ->join('profit_centers', 'profit_centers.profit_center_code', '=', 'client_profit_centers.profit_center_code')
            ->join('clients', 'clients.client_group_number', '=', 'client_profit_centers.client_group_number')
            ->leftJoin('users', 'users.id', '=', 'assignments.user_id')
            ->whereNotNull('assignments.user_id')
            ->whereIn('clients.classification_id', $classIdsForSellers)
            ->select(
                'assignments.user_id',
                'client_profit_centers.id as cpc_id',
                'client_profit_centers.profit_center_code',
                'profit_centers.profit_center_name as profit_center_name',
                'clients.client_group_number',
                'clients.client_name',
                'clients.classification_id',
                'users.first_name',
                'users.last_name',
                'users.email'
            )
            ->distinct()
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'target_fiscal_year' => $targetFy,
                'base_calendar_year' => $baseCalendarYear,
                'cap_month'          => $capMonth,
                'classes'            => [],
                'global'             => [],
                'by_seller'          => [],
                'by_pc'              => [],
                'pending_cases'      => [],
            ]);
        }

        $cpcIds  = $rows->pluck('cpc_id')->unique()->map(fn ($v) => (int) $v)->values()->all();
        $pcCodes = $rows->pluck('profit_center_code')->unique()->values()->all();

        // === 3) Budget Cases por CPC para el FY objetivo (incluye skip_budget) ===
        $casesRaw = DB::table('budget_cases')
            ->where('fiscal_year', $targetFy)
            ->whereIn('client_profit_center_id', $cpcIds)
            ->select(
                'client_profit_center_id',
                DB::raw('MAX(id) as id'),
                DB::raw('MAX(best_case)  as best_case'),
                DB::raw('MAX(worst_case) as worst_case'),
                DB::raw('MAX(skip_budget) as skip_budget')
            )
            ->groupBy('client_profit_center_id')
            ->get();

        $casesByCpc = [];
        foreach ($casesRaw as $c) {
            $cid = (int) $c->client_profit_center_id;
            $casesByCpc[$cid] = [
                'id'          => (int) $c->id,
                'best_case'   => $c->best_case !== null ? (float) $c->best_case : 0.0,
                'worst_case'  => $c->worst_case !== null ? (float) $c->worst_case : 0.0,
                'skip_budget' => (bool) ($c->skip_budget ?? false),
            ];
        }

        // === 4) Ventas YTD en m³ por CPC (enero..capMonth del año base) ===
        $ytdByCpcM3 = $this->loadSalesYtdM3ByCpc($cpcIds, $baseCalendarYear, $capMonth);

        // === 5) Seasonality YTD % por Profit Center Code ===
        $ytdPctByPc = [];
        foreach ($pcCodes as $pcCode) {
            $pcCodeStr = (string) $pcCode;
            $ytdPctByPc[$pcCodeStr] = $this->seasonalityYtdPct($pcCodeStr, $capMonth);
        }

        // === 6) Vorjahr: budget FY anterior completo en m³ por CPC ===
        $prevBudgetByCpc = $this->loadBudgetFyM3ByCpc($cpcIds, $prevFyStart);

        // === 7) Agregados por clasificación (A/B/PA/PB) y por vendedor ===

        $classMapForSellers = [
            1 => 'A',
            2 => 'B',
            6 => 'PA',
            7 => 'PB',
        ];

        $makeStats = function () {
            return [
                'total_cpcs'          => 0,
                'with_case'           => 0,
                'coverage_pct'        => 0.0,
                'base_m3_all'         => 0.0,
                'base_m3_with_case'   => 0.0,
                'best_m3'             => 0.0,
                'worst_m3'            => 0.0,
                'prev_m3'             => 0.0,
                'ytd_m3'              => 0.0,
                'ytd_annualized_m3'   => 0.0,
            ];
        };

        $statsByClass  = [];
        $statsGlobal   = $makeStats();
        $statsBySeller = []; // user_id => stats + user info
        $sellerPcs     = []; // user_id => [pc_code => pcStats]
        $pending       = []; // CPC sin budget case

        foreach ($rows as $r) {
            $userId    = (int) $r->user_id;
            $cpcId     = (int) $r->cpc_id;
            $pcCode    = (string) $r->profit_center_code;
            $pcName    = (string) ($r->profit_center_name ?? '');
            $classId   = (int) $r->classification_id;
            $classKey  = $classMapForSellers[$classId] ?? 'X';
            $clientCgn = (string) $r->client_group_number;

            if (!isset($statsByClass[$classKey])) {
                $statsByClass[$classKey] = $makeStats();
            }

            if (!isset($statsBySeller[$userId])) {
                $statsBySeller[$userId] = $makeStats() + [
                    'user_id'   => $userId,
                    'full_name' => $this->formatUserName($r->first_name, $r->last_name, $r->email),
                ];
            }

            if (!isset($sellerPcs[$userId])) {
                $sellerPcs[$userId] = [];
            }

            if (!isset($sellerPcs[$userId][$pcCode])) {
                $sellerPcs[$userId][$pcCode] = [
                    'profit_center_code'   => $pcCode,
                    'profit_center_name'   => $pcName,
                    'total_cpcs'           => 0,
                    'with_case'            => 0,
                    'coverage_pct'         => 0.0,
                    'base_m3_with_case'    => 0.0,
                    'best_m3'              => 0.0,
                    'worst_m3'             => 0.0,
                    'prev_m3'              => 0.0,
                    'ytd_m3'               => 0.0,
                    'ytd_annualized_m3'    => 0.0,
                    'clients'              => [],
                ];
            }

            $classStats  = &$statsByClass[$classKey];
            $sellerStats = &$statsBySeller[$userId];
            $global      = &$statsGlobal;
            $pcStats     = &$sellerPcs[$userId][$pcCode];

            // 7.1) Contar CPC
            $classStats['total_cpcs']  += 1;
            $sellerStats['total_cpcs'] += 1;
            $global['total_cpcs']      += 1;
            $pcStats['total_cpcs']     += 1;

            $hasCase    = isset($casesByCpc[$cpcId]);
            $caseBest   = $hasCase ? $casesByCpc[$cpcId]['best_case']  : 0.0;
            $caseWorst  = $hasCase ? $casesByCpc[$cpcId]['worst_case'] : 0.0;
            $skipBudget = $hasCase ? (bool) $casesByCpc[$cpcId]['skip_budget'] : false;

            // 7.2) YTD m³ de este CPC (enero..capMonth)
            $ytdM3  = $ytdByCpcM3[$cpcId] ?? 0.0;
            $ytdPct = $ytdPctByPc[$pcCode] ?? 0.0;

            // Base anualizada en m³ (Basis) para este CPC
            $baseM3 = ($ytdPct > 0.0 && $ytdM3 > 0.0)
                ? ($ytdM3 / ($ytdPct / 100.0))
                : 0.0;

            // 7.3) Best/Worst para este CPC:
            //  - si tiene case y NO está skip → normal
            //  - si está skip → 0 (no se planea nada)
            if ($hasCase && !$skipBudget) {
                $bestM3  = $baseM3 * (1.0 + $caseBest  / 100.0);
                $worstM3 = $baseM3 * (1.0 + $caseWorst / 100.0);
            } else {
                $bestM3  = 0.0;
                $worstM3 = 0.0;
            }

            // 7.4) Vorjahr (budget FY anterior) para este CPC
            $prevM3 = $prevBudgetByCpc[$cpcId] ?? 0.0;

            // 7.5) Acumular base_m3_all
            $classStats['base_m3_all']  += $baseM3;
            $sellerStats['base_m3_all'] += $baseM3;
            $global['base_m3_all']      += $baseM3;

            // 7.6) Acumular Vorjahr
            $classStats['prev_m3']  += $prevM3;
            $sellerStats['prev_m3'] += $prevM3;
            $global['prev_m3']      += $prevM3;
            $pcStats['prev_m3']     += $prevM3;

            // 7.7) YTD y anualizado
            $classStats['ytd_m3']            += $ytdM3;
            $classStats['ytd_annualized_m3'] += $baseM3;

            $sellerStats['ytd_m3']            += $ytdM3;
            $sellerStats['ytd_annualized_m3'] += $baseM3;

            $global['ytd_m3']            += $ytdM3;
            $global['ytd_annualized_m3'] += $baseM3;

            $pcStats['ytd_m3']            += $ytdM3;
            $pcStats['ytd_annualized_m3'] += $baseM3;

            // 7.8) Coverage y volumen:
            if ($hasCase) {
                // Siempre cuenta como "con case" (decidido), incluso si es skip
                $classStats['with_case']         += 1;
                $classStats['base_m3_with_case'] += $baseM3;

                $sellerStats['with_case']         += 1;
                $sellerStats['base_m3_with_case'] += $baseM3;

                $global['with_case']         += 1;
                $global['base_m3_with_case'] += $baseM3;

                $pcStats['with_case']         += 1;
                $pcStats['base_m3_with_case'] += $baseM3;

                // Sólo sumamos volumen de Best/Worst si NO está skip
                if (!$skipBudget) {
                    $classStats['best_m3']   += $bestM3;
                    $classStats['worst_m3']  += $worstM3;

                    $sellerStats['best_m3']  += $bestM3;
                    $sellerStats['worst_m3'] += $worstM3;

                    $global['best_m3']       += $bestM3;
                    $global['worst_m3']      += $worstM3;

                    $pcStats['best_m3']      += $bestM3;
                    $pcStats['worst_m3']     += $worstM3;
                }
            } else {
                // Pendiente → lo añadimos a la lista
                $pending[] = [
                    'client_group_number' => $clientCgn,
                    'client_name'         => $r->client_name,
                    'classification_id'   => $classId,
                    'classification'      => $this->classificationLetter($classId),
                    'profit_center_code'  => $pcCode,
                    'user_id'             => $userId,
                    'seller_name'         => $this->formatUserName($r->first_name, $r->last_name, $r->email),
                ];
            }

            // 7.9) Detalle de cliente dentro del PC (para la vista vendedor)
            $pcStats['clients'][] = [
                'client_group_number' => $clientCgn,
                'client_name'         => $r->client_name,
                'has_case'            => $hasCase,
                'skip_budget'         => $skipBudget,
                'prev_m3'             => $prevM3,
                'best_m3'             => $bestM3,
                'ytd_m3'              => $ytdM3,
                'ytd_annualized_m3'   => $baseM3,
            ];

            unset($classStats, $sellerStats, $global, $pcStats);
        }

        // === 7.bis) Integrar Basis por clase (A,B,C,D,PA,PB,X) usando ventas de TODOS los clientes ===

        foreach ($basisByClass as $classKey => $baseVal) {
            if (!isset($statsByClass[$classKey])) {
                $statsByClass[$classKey] = $makeStats();
            }
            $statsByClass[$classKey]['base_m3_all'] = (float) $baseVal;
        }

        // === 8) Calcular coverage_pct y formatear números ===
        $fmt = function ($n) {
            return (float) number_format((float) $n, 2, '.', '');
        };

        // Clases
        foreach ($statsByClass as $k => &$st) {
            $st['coverage_pct']      = $st['total_cpcs'] > 0 ? $fmt($st['with_case'] * 100.0 / $st['total_cpcs']) : 0.0;
            $st['base_m3_all']       = $fmt($st['base_m3_all']);
            $st['base_m3_with_case'] = $fmt($st['base_m3_with_case']);
            $st['best_m3']           = $fmt($st['best_m3']);
            $st['worst_m3']          = $fmt($st['worst_m3']);
            $st['prev_m3']           = $fmt($st['prev_m3']);
            $st['ytd_m3']            = $fmt($st['ytd_m3']);
            $st['ytd_annualized_m3'] = $fmt($st['ytd_annualized_m3']);
        }
        unset($st);

        // Global
        $statsGlobal['coverage_pct'] = $statsGlobal['total_cpcs'] > 0
            ? $fmt($statsGlobal['with_case'] * 100.0 / $statsGlobal['total_cpcs'])
            : 0.0;

        // Basis GLOBAL = computeGlobalBasisM3
        $statsGlobal['base_m3_all']       = $fmt($globalBasisM3);
        $statsGlobal['base_m3_with_case'] = $fmt($globalBasisM3);
        $statsGlobal['best_m3']           = $fmt($statsGlobal['best_m3']);
        $statsGlobal['worst_m3']          = $fmt($statsGlobal['worst_m3']);
        $statsGlobal['prev_m3']           = $fmt($statsGlobal['prev_m3']);
        $statsGlobal['ytd_m3']            = $fmt($statsGlobal['ytd_m3']);
        $statsGlobal['ytd_annualized_m3'] = $fmt($statsGlobal['ytd_annualized_m3']);

        // PCs por vendedor
        foreach ($sellerPcs as $uid => &$pcs) {
            foreach ($pcs as &$pc) {
                $pc['coverage_pct'] = $pc['total_cpcs'] > 0
                    ? $fmt($pc['with_case'] * 100.0 / $pc['total_cpcs'])
                    : 0.0;
                $pc['prev_m3']           = $fmt($pc['prev_m3']);
                $pc['best_m3']           = $fmt($pc['best_m3']);
                $pc['worst_m3']          = $fmt($pc['worst_m3']);
                $pc['base_m3_with_case'] = $fmt($pc['base_m3_with_case']);
                $pc['ytd_m3']            = $fmt($pc['ytd_m3']);
                $pc['ytd_annualized_m3'] = $fmt($pc['ytd_annualized_m3']);
            }
            unset($pc);
        }
        unset($pcs);

        // Vendedores
        foreach ($statsBySeller as $uid => &$st) {
            $st['coverage_pct']      = $st['total_cpcs'] > 0 ? $fmt($st['with_case'] * 100.0 / $st['total_cpcs']) : 0.0;
            $st['base_m3_all']       = $fmt($st['base_m3_all']);
            $st['base_m3_with_case'] = $fmt($st['base_m3_with_case']);
            $st['best_m3']           = $fmt($st['best_m3']);
            $st['worst_m3']          = $fmt($st['worst_m3']);
            $st['prev_m3']           = $fmt($st['prev_m3']);
            $st['ytd_m3']            = $fmt($st['ytd_m3']);
            $st['ytd_annualized_m3'] = $fmt($st['ytd_annualized_m3']);
            $st['pcs']               = array_values($sellerPcs[$uid] ?? []);
        }
        unset($st);

        // === 9) by_pc: resumen global por Profit Center ===

        $byPc = [];

        // 9.1) Partimos de los PCs que ya aparecen en sellerPcs (A/B/PA/PB)
        foreach ($sellerPcs as $uid => $pcs) {
            foreach ($pcs as $pcCode => $pcStats) {
                if (!isset($byPc[$pcCode])) {
                    $byPc[$pcCode] = [
                        'profit_center_code' => $pcStats['profit_center_code'],
                        'profit_center_name' => $pcStats['profit_center_name'],
                        'prev_m3'            => 0.0,
                        'best_m3'            => 0.0,
                        'worst_m3'           => 0.0,
                        'base_m3_with_case'  => 0.0,
                        'ytd_m3'             => 0.0,
                        'ytd_annualized_m3'  => 0.0,
                        'class_mix'          => [],
                        'pending_cases'      => [],
                    ];
                }
                $byPc[$pcCode]['prev_m3']           += (float) $pcStats['prev_m3'];
                $byPc[$pcCode]['best_m3']           += (float) $pcStats['best_m3'];   // ya viene 0 si skip
                $byPc[$pcCode]['worst_m3']          += (float) $pcStats['worst_m3'];  // idem
                $byPc[$pcCode]['base_m3_with_case'] += (float) $pcStats['base_m3_with_case'];
                $byPc[$pcCode]['ytd_m3']            += (float) $pcStats['ytd_m3'];
                $byPc[$pcCode]['ytd_annualized_m3'] += (float) $pcStats['ytd_annualized_m3'];
            }
        }

        // 9.2) Integrar class_mix (A,B,C,D,PA,PB,X) por PC
        foreach ($basisByPc as $pcCode => $classes) {
            if (!isset($byPc[$pcCode])) {
                $byPc[$pcCode] = [
                    'profit_center_code' => $pcCode,
                    'profit_center_name' => null,
                    'prev_m3'            => 0.0,
                    'best_m3'            => 0.0,
                    'worst_m3'           => 0.0,
                    'base_m3_with_case'  => 0.0,
                    'ytd_m3'             => 0.0,
                    'ytd_annualized_m3'  => 0.0,
                    'class_mix'          => [],
                    'pending_cases'      => [],
                ];
            }
            $mix = [];
            foreach ($classes as $classKey => $data) {
                $mix[$classKey] = [
                    'base_m3'   => $fmt($data['base_m3']),
                    'count_cpc' => (int) $data['count_cpc'],
                ];
            }
            $byPc[$pcCode]['class_mix'] = $mix;
        }

        // 9.3) Adjuntar casos pendientes a cada PC
        foreach ($pending as $p) {
            $pcCode = (string) $p['profit_center_code'];
            if (!isset($byPc[$pcCode])) {
                $byPc[$pcCode] = [
                    'profit_center_code' => $pcCode,
                    'profit_center_name' => null,
                    'prev_m3'            => 0.0,
                    'best_m3'            => 0.0,
                    'worst_m3'           => 0.0,
                    'base_m3_with_case'  => 0.0,
                    'ytd_m3'             => 0.0,
                    'ytd_annualized_m3'  => 0.0,
                    'class_mix'          => [],
                    'pending_cases'      => [],
                ];
            }
            $byPc[$pcCode]['pending_cases'][] = $p;
        }

        // Formatear algunos campos de by_pc
        foreach ($byPc as $pcCode => &$pc) {
            $pc['prev_m3']           = $fmt($pc['prev_m3']);
            $pc['best_m3']           = $fmt($pc['best_m3']);
            $pc['worst_m3']          = $fmt($pc['worst_m3']);
            $pc['base_m3_with_case'] = $fmt($pc['base_m3_with_case']);
            $pc['ytd_m3']            = $fmt($pc['ytd_m3']);
            $pc['ytd_annualized_m3'] = $fmt($pc['ytd_annualized_m3']);
        }
        unset($pc);

        // Ordenar PCs por código
        $byPcArr = array_values($byPc);
        usort($byPcArr, function ($a, $b) {
            return (int) $a['profit_center_code'] <=> (int) $b['profit_center_code'];
        });

        return response()->json([
            'target_fiscal_year' => $targetFy,
            'base_calendar_year' => $baseCalendarYear,
            'cap_month'          => $capMonth,
            'classes'            => $statsByClass,
            'global'             => $statsGlobal,
            'by_seller'          => array_values($statsBySeller),
            'by_pc'              => $byPcArr,
            'pending_cases'      => $pending,
        ]);
    }

    /* ======================= HELPERS PRIVADOS ======================= */

    private function fiscalYearFromDate(Carbon $date): int
    {
        $fy = $date->year;
        if ($date->month < 4) $fy -= 1; // FY empieza en abril
        return $fy;
    }

    /**
     * Mapea classification_id a letra.
     * Ajustá los IDs si en tu DB son otros.
     */
    private function classificationLetter(int $id): string
    {
        switch ($id) {
            case 1:
                return 'A';
            case 2:
                return 'B';
            case 3:
                return 'C';
            case 4:
                return 'D';
            case 6:
                return 'PA';
            case 7:
                return 'PB';
            default:
                return 'X'; // resto
        }
    }

    /**
     * Basisvolumen GLOBAL en m³:
     * - Usa TODAS las ventas del año calendario base (enero..capMonth)
     *   desde la tabla sales.cubic_meters.
     * - Para cada CPC: anualiza con seasonality.
     */
    private function computeGlobalBasisM3(int $baseCalendarYear, int $capMonth): float
    {
        if ($capMonth < 1) {
            return 0.0;
        }

        $rows = DB::table('sales')
            ->join('client_profit_centers', 'client_profit_centers.id', '=', 'sales.client_profit_center_id')
            ->where('sales.fiscal_year', $baseCalendarYear)
            ->whereBetween('sales.month', [1, $capMonth])
            ->groupBy('sales.client_profit_center_id', 'client_profit_centers.profit_center_code')
            ->select(
                'sales.client_profit_center_id as cpc_id',
                'client_profit_centers.profit_center_code',
                DB::raw('SUM(sales.cubic_meters) AS ytd_m3')
            )
            ->get();

        if ($rows->isEmpty()) {
            return 0.0;
        }

        $totalBase = 0.0;

        foreach ($rows as $r) {
            $pcCode = (string) $r->profit_center_code;
            $ytdM3  = (float) $r->ytd_m3;

            if ($ytdM3 <= 0.0) {
                continue;
            }

            $ytdPct = $this->seasonalityYtdPct($pcCode, $capMonth);
            if ($ytdPct <= 0.0) {
                continue;
            }

            $base = $ytdM3 / ($ytdPct / 100.0);
            $totalBase += $base;
        }

        return (float) $totalBase;
    }

    /**
     * Basisvolumen por Profit Center y clasificación (A,B,C,D,PA,PB,X)
     * usando ventas YTD (enero..capMonth) y seasonality.
     *
     * @return array{by_pc: array, by_class: array}
     */
    private function loadClassBasisByPcAndClass(int $baseCalendarYear, int $capMonth): array
    {
        if ($capMonth < 1) {
            return [
                'by_pc'   => [],
                'by_class'=> [],
            ];
        }

        $rows = DB::table('sales')
            ->join('client_profit_centers', 'client_profit_centers.id', '=', 'sales.client_profit_center_id')
            ->join('clients', 'clients.client_group_number', '=', 'client_profit_centers.client_group_number')
            ->where('sales.fiscal_year', $baseCalendarYear)
            ->whereBetween('sales.month', [1, $capMonth])
            ->groupBy('sales.client_profit_center_id', 'client_profit_centers.profit_center_code', 'clients.classification_id')
            ->select(
                'sales.client_profit_center_id as cpc_id',
                'client_profit_centers.profit_center_code',
                'clients.classification_id',
                DB::raw('SUM(sales.cubic_meters) AS ytd_m3')
            )
            ->get();

        $byPc    = [];
        $byClass = [];

        foreach ($rows as $r) {
            $pcCode  = (string) $r->profit_center_code;
            $classId = (int) $r->classification_id;
            $ytdM3   = (float) $r->ytd_m3;

            if ($ytdM3 <= 0.0) {
                continue;
            }

            $ytdPct = $this->seasonalityYtdPct($pcCode, $capMonth);
            if ($ytdPct <= 0.0) {
                continue;
            }

            $base = $ytdM3 / ($ytdPct / 100.0);
            $letter = $this->classificationLetter($classId);

            if (!isset($byPc[$pcCode])) {
                $byPc[$pcCode] = [];
            }
            if (!isset($byPc[$pcCode][$letter])) {
                $byPc[$pcCode][$letter] = [
                    'base_m3'   => 0.0,
                    'count_cpc' => 0,
                ];
            }

            $byPc[$pcCode][$letter]['base_m3']   += $base;
            $byPc[$pcCode][$letter]['count_cpc'] += 1;

            if (!isset($byClass[$letter])) {
                $byClass[$letter] = 0.0;
            }
            $byClass[$letter] += $base;
        }

        return [
            'by_pc'   => $byPc,
            'by_class'=> $byClass,
        ];
    }

    /**
     * Ventas YTD (enero..capMonth) SIEMPRE en m³ (sales.cubic_meters),
     * agrupadas por client_profit_center_id.
     *
     * @param  int[] $cpcIds
     * @param  int   $calendarYear
     * @param  int   $capMonth  0 = no hay datos
     * @return array [cpc_id => m3]
     */
    private function loadSalesYtdM3ByCpc(array $cpcIds, int $calendarYear, int $capMonth): array
    {
        if (empty($cpcIds) || $capMonth < 1) {
            return [];
        }

        $rows = DB::table('sales')
            ->whereIn('sales.client_profit_center_id', $cpcIds)
            ->where('sales.fiscal_year', $calendarYear)
            ->whereBetween('sales.month', [1, $capMonth])
            ->groupBy('sales.client_profit_center_id')
            ->select(
                'sales.client_profit_center_id',
                DB::raw('SUM(sales.cubic_meters) AS m3')
            )
            ->pluck('m3', 'sales.client_profit_center_id')
            ->toArray();

        $out = [];
        foreach ($rows as $cpcId => $m3) {
            $out[(int) $cpcId] = (float) $m3;
        }
        return $out;
    }

    /**
     * Budget FY completo en m³ por CPC:
     * - Año fiscal = $fyStart (Abr..Mar)
     * - budgets.volume * factor_to_m3 (unit_conversions)
     *
     * @param int[] $cpcIds
     * @param int   $fyStart  Año fiscal de inicio (ej: 2025 para WJ 2025/26)
     * @return array [cpc_id => m3]
     */
    private function loadBudgetFyM3ByCpc(array $cpcIds, int $fyStart): array
    {
        if (empty($cpcIds) || $fyStart <= 0) {
            return [];
        }

        $ucAgg = DB::table('unit_conversions')
            ->select(
                'profit_center_code',
                DB::raw('MAX(factor_to_m3) as factor_to_m3')
            )
            ->groupBy('profit_center_code');

        $rows = DB::table('budgets')
            ->join('client_profit_centers', 'client_profit_centers.id', '=', 'budgets.client_profit_center_id')
            ->leftJoinSub($ucAgg, 'uc', function ($j) {
                $j->on('uc.profit_center_code', '=', 'client_profit_centers.profit_center_code');
            })
            ->whereIn('budgets.client_profit_center_id', $cpcIds)
            ->where(function ($w) use ($fyStart) {
                $w->where(function ($a) use ($fyStart) {
                    $a->where('budgets.fiscal_year', $fyStart)
                      ->whereBetween('budgets.month', [4, 12]);
                })->orWhere(function ($b) use ($fyStart) {
                    $b->where('budgets.fiscal_year', $fyStart + 1)
                      ->whereBetween('budgets.month', [1, 3]);
                });
            })
            ->groupBy('budgets.client_profit_center_id')
            ->select(
                'budgets.client_profit_center_id',
                DB::raw('SUM(budgets.volume * COALESCE(uc.factor_to_m3, 1)) AS m3')
            )
            ->pluck('m3', 'budgets.client_profit_center_id')
            ->toArray();

        $out = [];
        foreach ($rows as $cpcId => $m3) {
            $out[(int) $cpcId] = (float) $m3;
        }
        return $out;
    }

    /**
     * Seasonality YTD % para un PC:
     * - Jan..Mar: prev FY
     * - Apr..cap: último FY
     */
    private function seasonalityYtdPct(string $profitCenterCode, int $capMonth): float
    {
        if ($capMonth < 1) return 0.0;

        $seasonMaxRow = DB::table('seasonalities')
            ->where('profit_center_code', $profitCenterCode)
            ->orderBy('fiscal_year', 'desc')
            ->first();

        if (!$seasonMaxRow) {
            return 0.0;
        }

        $seasonPrevRow = DB::table('seasonalities')
            ->where('profit_center_code', $profitCenterCode)
            ->where('fiscal_year', '<', $seasonMaxRow->fiscal_year)
            ->orderBy('fiscal_year', 'desc')
            ->first();

        $seasonMax  = $this->mapSeasonRow($seasonMaxRow);
        $seasonPrev = $this->mapSeasonRow($seasonPrevRow ?? $seasonMaxRow);

        $names = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $ytdPct = 0.0;
        for ($m = 1; $m <= $capMonth; $m++) {
            $nm  = $names[$m];
            $pct = ($m <= 3) ? ($seasonPrev[$nm] ?? 0.0) : ($seasonMax[$nm] ?? 0.0);
            $ytdPct += (float) $pct;
        }

        return (float) $ytdPct;
    }

    private function mapSeasonRow(object $row): array
    {
        $out = [];
        $map = [
            'Jan' => 'jan', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'apr',
            'May' => 'may', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'aug',
            'Sep' => 'sep', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'dec',
        ];
        foreach ($map as $cap => $low) {
            $v = isset($row->{$low}) ? $row->{$low} : (isset($row->{$cap}) ? $row->{$cap} : 0.0);
            if (is_string($v)) $v = str_replace(',', '.', $v);
            $out[$cap] = (float) $v;
        }
        return $out;
    }

    private function formatUserName(?string $first, ?string $last, ?string $email): string
    {
        $first = trim((string) $first);
        $last  = trim((string) $last);
        if ($first !== '' || $last !== '') {
            return trim($first . ' ' . $last);
        }
        return (string) $email ?: 'Unbekannt';
    }
}
