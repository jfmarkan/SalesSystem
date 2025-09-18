<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyAnalyticsController extends Controller
{
    /**
     * Returns the tree root or an empty array if node_id != root.
     * UI labels are in German.
     */
    public function tree(Request $request){
        $nodeId = $request->input('node_id', 'root');

        if ($nodeId === 'root') {
            return response()->json([[
                'id'           => 'company_main',
                'label'        => config('app.company_name', env('COMPANY_NAME', 'Steinbacher Dämmstoffe')),
                'has_children' => true,
                'type'         => 'company',
                'children'     => $this->treeTeams(),
            ]]);
        }

        return response()->json([]);
    }

    /**
     * Builds the team -> active users -> PCs -> clients tree.
     * IMPORTANT: A user is included in a team only if the pivot (team_members) row is NOT soft-deleted
     * (i.e., team_members.deleted_at IS NULL). Assignments will NOT re-introduce removed members.
     * Manager (team.manager_user_id) is always shown (independent from team_members).
     * UI-visible user suffix is in German: " (Teamleiter)".
     */
    private function treeTeams(): array{
        $teams = DB::table('teams')
            ->select('id', 'name', 'manager_user_id')
            ->orderBy('name')
            ->get();

        if ($teams->isEmpty()) return [];

        $teamIds = $teams->pluck('id')->all();

        $teamMembers = DB::table('team_members')
            ->whereIn('team_members.team_id', $teamIds)
            ->whereNull('team_members.deleted_at')
            ->select('team_members.team_id', 'team_members.user_id', 'team_members.role')
            ->get();

        $managerPerTeam = [];
        foreach ($teams as $t) {
            if ($t->manager_user_id) {
                $managerPerTeam[$t->id] = (int)$t->manager_user_id;
            }
        }

        $activeMembers = [];
        foreach ($teamMembers as $m) {
            $role = strtolower((string)$m->role) === 'manager' ? 'manager' : 'seller';
            $activeMembers[$m->team_id][(int)$m->user_id] = $role;
        }

        $usersByTeam = [];
        $userIdsAll  = [];
        foreach ($teams as $t) {
            $tid = (int)$t->id;
            if (!empty($managerPerTeam[$tid])) {
                $mgrId = $managerPerTeam[$tid];
                $usersByTeam[$tid][$mgrId] = 'manager';
                $userIdsAll[] = $mgrId;
            }
            if (!empty($activeMembers[$tid])) {
                foreach ($activeMembers[$tid] as $uid => $role) {
                    $usersByTeam[$tid][$uid] = $role;
                    $userIdsAll[] = (int)$uid;
                }
            }
        }

        $assignments = DB::table('assignments')
            ->whereIn('assignments.team_id', $teamIds)
            ->leftJoin('client_profit_centers', 'client_profit_centers.id', '=', 'assignments.client_profit_center_id')
            ->leftJoin('profit_centers', 'profit_centers.profit_center_code', '=', 'client_profit_centers.profit_center_code')
            ->leftJoin('clients', 'clients.client_group_number', '=', 'client_profit_centers.client_group_number')
            ->select(
                'assignments.team_id',
                'assignments.user_id',
                'client_profit_centers.profit_center_code',
                'profit_centers.profit_center_name',
                'clients.client_group_number',
                'clients.client_name'
            )
            ->get();

        $userIdsAll = array_values(array_unique($userIdsAll));
        $display = $this->fetchUsersDisplay($userIdsAll);

        $map = [];
        foreach ($assignments as $r) {
            if (!$r->user_id) continue;

            $tid = (int)$r->team_id;
            $uid = (int)$r->user_id;

            $isVisibleInTeam = isset($usersByTeam[$tid][$uid]);
            if (!$isVisibleInTeam) continue;

            $pcCode = (string)$r->profit_center_code;
            $pcName = (string)($r->profit_center_name ?? $pcCode);
            $cgn    = $r->client_group_number ? (string)$r->client_group_number : null;
            $cname  = $r->client_name ? (string)$r->client_name : null;

            $map[$tid]                  = $map[$tid]                  ?? [];
            $map[$tid][$uid]            = $map[$tid][$uid]            ?? [];
            $map[$tid][$uid][$pcCode]   = $map[$tid][$uid][$pcCode]   ?? ['pc_name' => $pcName, 'clients' => [], '_seen' => []];

            if ($cgn && !isset($map[$tid][$uid][$pcCode]['_seen'][$cgn])) {
                $map[$tid][$uid][$pcCode]['clients'][] = [
                    'client_group_number' => $cgn,
                    'client_name'         => $cname ?? $cgn
                ];
                $map[$tid][$uid][$pcCode]['_seen'][$cgn] = true;
            }
        }

        foreach ($map as $tid => &$users) {
            foreach ($users as $uid => &$pcs) {
                foreach ($pcs as $pc => &$info) {
                    unset($info['_seen']);
                    usort($info['clients'], fn($a, $b) => strcmp($a['client_name'], $b['client_name']));
                }
                ksort($pcs);
            }
        }
        unset($users, $pcs, $info);

        $out = [];
        foreach ($teams as $t) {
            $teamNode = [
                'id'           => 'team_' . $t->id,
                'label'        => $t->name,
                'has_children' => true,
                'type'         => 'team',
                'children'     => [],
            ];

            $uids = array_keys($usersByTeam[$t->id] ?? []);
            sort($uids);

            foreach ($uids as $uid) {
                $isManager = ($usersByTeam[$t->id][$uid] ?? 'seller') === 'manager';
                $labelUser = $display[$uid] ?? ('User ' . $uid);

                $userNode = [
                    'id'           => 'user_' . $uid . '_t' . $t->id,
                    'label'        => $labelUser . ($isManager ? ' (Teamleiter)' : ''),
                    'has_children' => true,
                    'type'         => 'user',
                    'children'     => [],
                ];

                if (isset($map[$t->id][$uid])) {
                    foreach ($map[$t->id][$uid] as $pcCode => $info) {
                        $pcNode = [
                            'id'           => 'pc_' . $pcCode . '_u' . $uid . '_t' . $t->id,
                            'label'        => $info['pc_name'],
                            'has_children' => true,
                            'type'         => 'pc',
                            'children'     => [],
                        ];
                        foreach ($info['clients'] as $cl) {
                            $pcNode['children'][] = [
                                'id'           => 'client_' . $cl['client_group_number'] . '_pc' . $pcCode . '_u' . $uid . '_t' . $t->id,
                                'label'        => $cl['client_name'],
                                'has_children' => false,
                                'type'         => 'client',
                            ];
                        }
                        $userNode['children'][] = $pcNode;
                    }
                }

                $teamNode['children'][] = $userNode;
            }

            $out[] = $teamNode;
        }

        return $out;
    }

    /**
     * Fetches a display label for each user id.
     */
    private function fetchUsersDisplay(array $ids): array{
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) return [];

        $rows = DB::table('users')->whereIn('users.id', $ids)->get();

        $map = [];
        foreach ($rows as $u) {
            $id  = (int)($u->id ?? 0);
            $arr = (array)$u;

            $pick = function(array $keys) use ($arr): string {
                foreach ($keys as $k) {
                    if (array_key_exists($k, $arr)) {
                        $v = trim((string)$arr[$k]);
                        if ($v !== '') return $v;
                    }
                }
                return '';
            };

            $last  = $pick(['last_name','apellido','apellidos','surname','family_name','LastName','lastName']);
            $first = $pick(['first_name','nombre','given_name','FirstName','firstName']);

            if ($last !== '' || $first !== '') {
                $label = trim($last . ($last !== '' && $first !== '' ? ', ' : '') . $first, ' ,');
            } else {
                $label = $pick(['full_name','display_name','name','username','email']);
                if ($label === '') $label = 'User ' . $id;
            }
            $map[$id] = $label;
        }
        foreach ($ids as $id) {
            if (!isset($map[$id])) $map[$id] = 'User ' . $id;
        }
        return $map;
    }

    /**
     * Parses a node id into context pieces.
     */
    private function parseNodeId(string $nodeId): array{
        if ($nodeId === 'company_main') return ['type' => 'company'];
        if (preg_match('/^team_(\d+)$/', $nodeId, $m)) return ['type' => 'team', 'team_id' => (int)$m[1]];
        if (preg_match('/^user_(\d+)_t(\d+)$/', $nodeId, $m)) return ['type' => 'user', 'user_id' => (int)$m[1], 'team_id' => (int)$m[2]];
        if (preg_match('/^pc_(.+)_u(\d+)_t(\d+)$/', $nodeId, $m)) return ['type' => 'pc', 'pc_code' => $m[1], 'user_id' => (int)$m[2], 'team_id' => (int)$m[3]];
        if (preg_match('/^client_(.+)_pc(.+)_u(\d+)_t(\d+)$/', $nodeId, $m)) {
            return [
                'type'                 => 'client',
                'client_group_number'  => $m[1],
                'pc_code'              => $m[2],
                'user_id'              => (int)$m[3],
                'team_id'              => (int)$m[4],
            ];
        }
        return ['type' => 'unknown'];
    }

    private function fmt($n): string{ return number_format((float)$n, 0, ',', '.'); }
    private function fmtArr(array $nums): array{ return array_map(fn($v) => $this->fmt($v), $nums); }
    private function fmtRows($rows, array $fields = ['units','m3','euro']){
        $out = [];
        foreach ($rows as $r) {
            $row = (array)$r;
            foreach ($fields as $f) if (array_key_exists($f, $row)) $row[$f] = $this->fmt($row[$f]);
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Returns active user ids for a team.
     */
    private function teamUserIds(int $teamId): array{
        $ids = [];

        $mgr = DB::table('teams')->where('id', $teamId)->value('manager_user_id');
        if ($mgr) $ids[] = (int)$mgr;

        $members = DB::table('team_members')
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->pluck('user_id')->map(fn($u)=>(int)$u)->all();

        $assignees = DB::table('assignments')
            ->where('assignments.team_id', $teamId)
            ->whereNotNull('assignments.user_id')
            ->join('team_members', function($j){
                $j->on('team_members.team_id','=','assignments.team_id')
                  ->on('team_members.user_id','=','assignments.user_id');
            })
            ->whereNull('team_members.deleted_at')
            ->distinct()
            ->pluck('assignments.user_id')->map(fn($u)=>(int)$u)->all();

        return array_values(array_unique(array_filter(array_merge($ids, $members, $assignees))));
    }

    /**
     * Returns monthly series for the given node context within a fiscal year.
     */
public function series(Request $request){
    $nodeId = (string)$request->query('node_id', '');
    if ($nodeId === '') return response()->json(['error'=>'node_id ist erforderlich'], 422);

    $ctx = $this->parseNodeId($nodeId);
    $fyStart = (int) $request->query('fiscal_year', (int)date('Y'));
    if ($fyStart < 2024) $fyStart = 2024;

    $monthsOrdered = [4,5,6,7,8,9,10,11,12,1,2,3];
    $labels = [];
    foreach ($monthsOrdered as $m) {
        $y = ($m >= 4) ? $fyStart : ($fyStart + 1);
        $labels[] = sprintf('%04d-%02d', $y, $m);
    }
    $fyLabel = 'WJ ' . $fyStart . '/' . substr((string)($fyStart+1), -2);

    $cpcQuery = DB::table('assignments')
        ->join('client_profit_centers', 'client_profit_centers.id', '=', 'assignments.client_profit_center_id')
        ->select('client_profit_centers.id', 'client_profit_centers.profit_center_code')
        ->distinct();

    if (($ctx['type'] ?? '') === 'team') {
        $cpcQuery->where('assignments.team_id', $ctx['team_id']);
    } elseif (($ctx['type'] ?? '') === 'user') {
        $cpcQuery->where('assignments.team_id', $ctx['team_id'])
                ->where('assignments.user_id', $ctx['user_id']);
    } elseif (($ctx['type'] ?? '') === 'pc') {
        $cpcQuery->where('assignments.team_id', $ctx['team_id'])
                ->where('assignments.user_id', $ctx['user_id'])
                ->where('client_profit_centers.profit_center_code', $ctx['pc_code']);
    } elseif (($ctx['type'] ?? '') === 'client') {
        $cpcQuery->join('clients', 'clients.client_group_number', '=', 'client_profit_centers.client_group_number')
                ->where('assignments.team_id', $ctx['team_id'])
                ->where('assignments.user_id', $ctx['user_id'])
                ->where('client_profit_centers.profit_center_code', $ctx['pc_code'])
                ->where('clients.client_group_number', $ctx['client_group_number']);
    }

    $cpcs = $cpcQuery->distinct()->get();
    if ($cpcs->isEmpty()) {
        return response()->json($this->emptyFiscalSeries($fyStart, $ctx));
    }

    $cpcIds = collect($cpcs)->pluck('id')->unique()->values()->all();

    // Helper FY
    $applyFY = function($q, $tbl, int $fyStart) {
        $q->where(function($w) use ($tbl, $fyStart) {
            $w->where(function($a) use ($tbl, $fyStart) {
                $a->where($tbl.'.fiscal_year', $fyStart)->whereBetween($tbl.'.month', [4,12]);
            })->orWhere(function($b) use ($tbl, $fyStart) {
                $b->where($tbl.'.fiscal_year', $fyStart+1)->whereBetween($tbl.'.month', [1,3]);
            });
        });
    };

    $fyIndex = fn(int $rowFy, int $month) => ($rowFy === $fyStart) ? max(0, min(11, $month - 4)) : max(0, min(11, 8 + $month));
    $make12 = fn() => array_fill(0, 12, 0.0);

    // Unit conversions (latest per PC)
    $ucAgg = DB::table('unit_conversions')
        ->select(
            'profit_center_code',
            DB::raw('MAX(factor_to_m3) as factor_to_m3'),
            DB::raw('MAX(factor_to_euro) as factor_to_euro')
        )->groupBy('profit_center_code');

    // SALES
    $salesQ = DB::table('sales')
        ->join('client_profit_centers', 'client_profit_centers.id', '=', 'sales.client_profit_center_id')
        ->leftJoinSub($ucAgg, 'uc', fn($j) => $j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code'))
        ->whereIn('sales.client_profit_center_id', $cpcIds);
    $applyFY($salesQ, 'sales', $fyStart);

    $sales = $make12(); $salesM3 = $make12(); $salesEur = $make12();
    foreach ($salesQ->select(
        'sales.fiscal_year','sales.month',
        DB::raw('SUM(sales.volume) as units'),
        DB::raw('SUM(sales.volume * COALESCE(uc.factor_to_m3,1)) as m3'),
        DB::raw('SUM(sales.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as euro')
    )->groupBy('sales.fiscal_year','sales.month')->get() as $r) {
        $i = $fyIndex((int)$r->fiscal_year, (int)$r->month);
        $sales[$i] = (float)$r->units;
        $salesM3[$i] = (float)$r->m3;
        $salesEur[$i] = (float)$r->euro;
    }

    // BUDGETS
    $budgets = $make12(); $budgetM3 = $make12(); $budgetEur = $make12();
    $budQ = DB::table('budgets')
        ->join('client_profit_centers', 'client_profit_centers.id', '=', 'budgets.client_profit_center_id')
        ->leftJoinSub($ucAgg, 'uc', fn($j) => $j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code'))
        ->whereIn('budgets.client_profit_center_id', $cpcIds);
    $applyFY($budQ, 'budgets', $fyStart);

    foreach ($budQ->select(
        'budgets.fiscal_year','budgets.month',
        DB::raw('SUM(budgets.volume) as units'),
        DB::raw('SUM(budgets.volume * COALESCE(uc.factor_to_m3,1)) as m3'),
        DB::raw('SUM(budgets.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as euro')
    )->groupBy('budgets.fiscal_year','budgets.month')->get() as $r) {
        $i = $fyIndex((int)$r->fiscal_year, (int)$r->month);
        $budgets[$i] = (float)$r->units;
        $budgetM3[$i] = (float)$r->m3;
        $budgetEur[$i] = (float)$r->euro;
    }

    // FORECASTS (latest per author)
    $fcBase = DB::table('forecasts')
        ->select('client_profit_center_id','fiscal_year','month','user_id', DB::raw('MAX(version) as max_version'))
        ->groupBy('client_profit_center_id','fiscal_year','month','user_id');

    $fc = $make12(); $fcM3 = $make12(); $fcEur = $make12();
    $fcQ = DB::table('forecasts')
        ->joinSub($fcBase, 'lv', function($j) {
            $j->on('forecasts.client_profit_center_id','=','lv.client_profit_center_id')
            ->on('forecasts.fiscal_year','=','lv.fiscal_year')
            ->on('forecasts.month','=','lv.month')
            ->on('forecasts.user_id','=','lv.user_id')
            ->on('forecasts.version','=','lv.max_version');
        })
        ->join('client_profit_centers', 'client_profit_centers.id', '=', 'forecasts.client_profit_center_id')
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code'))
        ->whereIn('forecasts.client_profit_center_id', $cpcIds);
    $applyFY($fcQ, 'forecasts', $fyStart);

    foreach ($fcQ->select(
        'forecasts.fiscal_year','forecasts.month',
        DB::raw('SUM(forecasts.volume) as units'),
        DB::raw('SUM(forecasts.volume * COALESCE(uc.factor_to_m3,1)) as m3'),
        DB::raw('SUM(forecasts.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as euro')
    )->groupBy('forecasts.fiscal_year','forecasts.month')->get() as $r) {
        $i = $fyIndex((int)$r->fiscal_year, (int)$r->month);
        $fc[$i] = (float)$r->units;
        $fcM3[$i] = (float)$r->m3;
        $fcEur[$i] = (float)$r->euro;
    }

    // === EXTRA QUOTA: remaining = assigned - won ===
    $eqUserIds = null;
    if (in_array($ctx['type'], ['user','pc','client']))      $eqUserIds = [$ctx['user_id']];
    elseif ($ctx['type'] === 'team')                         $eqUserIds = $this->teamUserIds($ctx['team_id']);

    // ASSIGNED (FY)
    $eqAssignedQ = DB::table('extra_quota_assignments as eq')
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','eq.profit_center_code'))
        ->where('eq.fiscal_year', $fyStart)->where('eq.is_published', 1)
        ->when($eqUserIds, fn($q)=>$q->whereIn('eq.user_id', $eqUserIds))
        ->when(in_array($ctx['type'], ['pc','client']), fn($q)=>$q->where('eq.profit_center_code', $ctx['pc_code']))
        ->selectRaw('
            COALESCE(SUM(eq.volume),0) as units,
            COALESCE(SUM(eq.volume * COALESCE(uc.factor_to_m3,1)),0) as m3,
            COALESCE(SUM(eq.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)),0) as euro
        ')
        ->first();

    $assignedU = (float)($eqAssignedQ->units ?? 0.0);
    $assignedM = (float)($eqAssignedQ->m3 ?? 0.0);
    $assignedE = (float)($eqAssignedQ->euro ?? 0.0);

    // WON converted (sum de extra_quota_budgets ganados)
    $wonConvQ = DB::table('sales_opportunities as s')
        ->where('s.status','won')
        ->when($eqUserIds, fn($q)=>$q->whereIn('s.user_id', $eqUserIds))
        ->when(in_array($ctx['type'], ['pc','client']), fn($q)=>$q->where('s.profit_center_code', $ctx['pc_code']))
        ->join('extra_quota_budgets as b', function($j){
            $j->on('b.opportunity_group_id','=','s.opportunity_group_id')
            ->on('b.version','=','s.version');
        })
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','s.profit_center_code'));
    $applyFY($wonConvQ, 'b', $fyStart);

    $wonConvRow = $wonConvQ->selectRaw('
        COALESCE(SUM(b.volume),0) as units,
        COALESCE(SUM(b.volume * COALESCE(uc.factor_to_m3,1)),0) as m3,
        COALESCE(SUM(b.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)),0) as euro
    ')->first();

    $wonU = (float)($wonConvRow->units ?? 0.0);
    $wonM = (float)($wonConvRow->m3 ?? 0.0);
    $wonE = (float)($wonConvRow->euro ?? 0.0);

    // REMAINING
    $extraUnits = max(0.0, $assignedU - $wonU);
    $extraM3    = max(0.0, $assignedM - $wonM);
    $extraEur   = max(0.0, $assignedE - $wonE);

    // Distribute ONLY remaining
    $applyExtraQuota = in_array($ctx['type'], ['company', 'team', 'user', 'pc']);
    $distribute = function($base, $extra) {
        $total = array_sum($base);
        if ($extra == 0) return $base;
        if ($total == 0) return array_map(fn($v)=>$v + ($extra/12), $base);
        foreach ($base as $i => $v) {
            $frac = $v > 0 ? $v / $total : 0;
            $base[$i] = $v + $extra * $frac;
        }
        return $base;
    };

    $budgetsAdj   = $applyExtraQuota ? $distribute($budgets,  $extraUnits) : $budgets;
    $budgetM3Adj  = $applyExtraQuota ? $distribute($budgetM3, $extraM3)    : $budgetM3;
    $budgetEurAdj = $applyExtraQuota ? $distribute($budgetEur,$extraEur)   : $budgetEur;

    return response()->json([
        'context' => $ctx,
        'fy_start' => $fyStart,
        'fy_label' => $fyLabel,
        'months' => $labels,
        'unit_mode_allowed' => in_array($ctx['type'], ['pc','client']),
        'sales' => [
            'units' => $this->fmtArr($sales),
            'm3'    => $this->fmtArr($salesM3),
            'euro'  => $this->fmtArr($salesEur),
        ],
        'budgets' => [
            'units' => $this->fmtArr($budgetsAdj),
            'm3'    => $this->fmtArr($budgetM3Adj),
            'euro'  => $this->fmtArr($budgetEurAdj),
        ],
        'forecasts' => [
            'units' => $this->fmtArr($fc),
            'm3'    => $this->fmtArr($fcM3),
            'euro'  => $this->fmtArr($fcEur),
        ],
        // extra = remaining (assigned - won)
        'extra_quotas' => [
            'units' => $this->fmt($extraUnits),
            'm3'    => $this->fmt($extraM3),
            'euro'  => $this->fmt($extraEur),
        ],
        'totals' => [
            'sales' => [
                'units' => $this->fmt(array_sum($sales)),
                'm3'    => $this->fmt(array_sum($salesM3)),
                'euro'  => $this->fmt(array_sum($salesEur)),
            ],
            'budgets' => [
                'units' => $this->fmt(array_sum($budgetsAdj)),
                'm3'    => $this->fmt(array_sum($budgetM3Adj)),
                'euro'  => $this->fmt(array_sum($budgetEurAdj)),
            ],
            'forecasts' => [
                'units' => $this->fmt(array_sum($fc)),
                'm3'    => $this->fmt(array_sum($fcM3)),
                'euro'  => $this->fmt(array_sum($fcEur)),
            ],
        ],
    ]);
}


private function emptyFiscalSeries(int $fyStart, array $ctx): array{
        $monthsOrdered = [4,5,6,7,8,9,10,11,12,1,2,3];
        $labels = [];
        foreach ($monthsOrdered as $m) {
            $y = ($m >= 4) ? $fyStart : ($fyStart + 1);
            $labels[] = sprintf('%04d-%02d', $y, $m);
        }
        $zeros = array_fill(0, 12, 0.0);
        return [
            'context'           => $ctx,
            'fy_start'          => $fyStart,
            'fy_label'          => 'WJ ' . $fyStart . '/' . substr((string)($fyStart+1), -2),
            'months'            => $labels,
            'unit_mode_allowed' => in_array($ctx['type'] ?? '', ['pc','client']),
            'sales'             => ['units'=>$this->fmtArr($zeros),'m3'=>$this->fmtArr($zeros),'euro'=>$this->fmtArr($zeros)],
            'budgets'           => ['units'=>$this->fmtArr($zeros),'m3'=>$this->fmtArr($zeros),'euro'=>$this->fmtArr($zeros)],
            'forecasts'         => ['units'=>$this->fmtArr($zeros),'m3'=>$this->fmtArr($zeros),'euro'=>$this->fmtArr($zeros)],
            'extra_quotas'      => ['units'=>$this->fmt(0),'m3'=>$this->fmt(0),'euro'=>$this->fmt(0)],
            'totals' => [
                'sales'     => ['units'=>$this->fmt(0),'m3'=>$this->fmt(0),'euro'=>$this->fmt(0)],
                'budgets'   => ['units'=>$this->fmt(0),'m3'=>$this->fmt(0),'euro'=>$this->fmt(0)],
                'forecasts' => ['units'=>$this->fmt(0),'m3'=>$this->fmt(0),'euro'=>$this->fmt(0)],
            ],
        ];
    }

    /**
     * Aggregated totals for the selected context and period.
     */
    public function totals(Request $request){
        $nodeId = (string)$request->input('node_id', '');
        if ($nodeId === '') return response()->json(['error' => 'node_id ist erforderlich'], 422);

        $ctx  = $this->parseNodeId($nodeId);
        $fy   = $request->integer('fiscal_year');
        $from = $request->input('from');
        $to   = $request->input('to');

        $cpcs = DB::table('assignments')
            ->join('client_profit_centers', 'client_profit_centers.id', '=', 'assignments.client_profit_center_id')
            ->select('client_profit_centers.id as cpc_id', 'client_profit_centers.profit_center_code')
            ->when($ctx['type'] === 'team', function($q) use ($ctx) {
                $q->where('assignments.team_id', $ctx['team_id']);
            })
            ->when($ctx['type'] === 'user', function($q) use ($ctx) {
                $q->where('assignments.team_id', $ctx['team_id'])
                  ->where('assignments.user_id', $ctx['user_id']);
            })
            ->when($ctx['type'] === 'pc', function($q) use ($ctx) {
                $q->where('assignments.team_id', $ctx['team_id'])
                  ->where('assignments.user_id', $ctx['user_id'])
                  ->where('client_profit_centers.profit_center_code', $ctx['pc_code']);
            })
            ->when($ctx['type'] === 'client', function($q) use ($ctx) {
                $q->join('clients', 'clients.client_group_number', '=', 'client_profit_centers.client_group_number')
                  ->where('assignments.team_id', $ctx['team_id'])
                  ->where('assignments.user_id', $ctx['user_id'])
                  ->where('client_profit_centers.profit_center_code', $ctx['pc_code'])
                  ->where('clients.client_group_number', $ctx['client_group_number']);
            })
            ->distinct();

        $applyPeriod = function($q, $tbl) use ($fy, $from, $to) {
            if ($fy) {
                $q->where(function($w) use ($tbl, $fy) {
                    $w->where(function($a) use ($tbl, $fy) {
                        $a->where($tbl.'.fiscal_year', $fy)
                          ->whereBetween($tbl.'.month', [4,12]);
                    })->orWhere(function($b) use ($tbl, $fy) {
                        $b->where($tbl.'.fiscal_year', $fy+1)
                          ->whereBetween($tbl.'.month', [1,3]);
                    });
                });
            } elseif ($from && $to && preg_match('/^\d{4}-\d{2}$/',$from) && preg_match('/^\d{4}-\d{2}$/',$to)) {
                [$yf,$mf] = array_map('intval', explode('-', $from));
                [$yt,$mt] = array_map('intval', explode('-', $to));
                $q->whereRaw('(' . $tbl . '.fiscal_year*100 + ' . $tbl . '.month) between ? and ?', [$yf*100+$mf, $yt*100+$mt]);
            }
        };

        $ucAgg = DB::table('unit_conversions')
            ->select(
                'profit_center_code',
                DB::raw('MAX(factor_to_m3) as factor_to_m3'),
                DB::raw('MAX(factor_to_euro) as factor_to_euro')
            )->groupBy('profit_center_code');

        // SALES
        $sales = DB::table('sales')
            ->joinSub($cpcs, 'cpcs', function($j){ $j->on('cpcs.cpc_id', '=', 'sales.client_profit_center_id'); })
            ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','cpcs.profit_center_code'));
        $applyPeriod($sales, 'sales');
        $salesRows = $sales->select(
                'cpcs.profit_center_code',
                DB::raw('SUM(sales.volume) as units'),
                DB::raw('SUM(sales.volume * COALESCE(uc.factor_to_m3,1)) as m3'),
                DB::raw('SUM(sales.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as euro')
            )
            ->groupBy('cpcs.profit_center_code')
            ->get();

        // BUDGETS
        $budgets = DB::table('budgets')
            ->joinSub($cpcs, 'cpcs', function($j){ $j->on('cpcs.cpc_id', '=', 'budgets.client_profit_center_id'); })
            ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','cpcs.profit_center_code'));
        $applyPeriod($budgets, 'budgets');
        $budgetRows = $budgets->select(
                'cpcs.profit_center_code',
                DB::raw('SUM(budgets.volume) as units'),
                DB::raw('SUM(budgets.volume * COALESCE(uc.factor_to_m3,1)) as m3'),
                DB::raw('SUM(budgets.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as euro')
            )
            ->groupBy('cpcs.profit_center_code')
            ->get();

        // FORECASTS (latest per author)
        $fcBase = DB::table('forecasts')
            ->select(
                'forecasts.client_profit_center_id',
                'forecasts.fiscal_year',
                'forecasts.month',
                'forecasts.user_id',
                DB::raw('MAX(forecasts.version) as max_version')
            )
            ->groupBy('forecasts.client_profit_center_id', 'forecasts.fiscal_year', 'forecasts.month', 'forecasts.user_id');

        $forecasts = DB::table('forecasts')
            ->joinSub($fcBase, 'lv', function($j) {
                $j->on('forecasts.client_profit_center_id', '=', 'lv.client_profit_center_id')
                  ->on('forecasts.fiscal_year', '=', 'lv.fiscal_year')
                  ->on('forecasts.month', '=', 'lv.month')
                  ->on('forecasts.user_id', '=', 'lv.user_id')
                  ->on('forecasts.version', '=', 'lv.max_version');
            })
            ->joinSub($cpcs, 'cpcs', function($j){ $j->on('cpcs.cpc_id', '=', 'forecasts.client_profit_center_id'); })
            ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','cpcs.profit_center_code'));
        $applyPeriod($forecasts, 'forecasts');

        $forecastRows = $forecasts->select(
                'cpcs.profit_center_code',
                DB::raw('SUM(forecasts.volume) as units'),
                DB::raw('SUM(forecasts.volume * COALESCE(uc.factor_to_m3,1)) as m3'),
                DB::raw('SUM(forecasts.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as euro')
            )
            ->groupBy('cpcs.profit_center_code')
            ->get();

        $sum = fn($rows, $f) => (float)collect($rows)->sum($f);
        $includeUnits = in_array($ctx['type'], ['pc','client']);

        // EXTRA QUOTAS (assignments)
        $nowY = (int)date('Y'); $nowM = (int)date('n');
        $currentFy = ($nowM >= 4) ? $nowY : ($nowY - 1);
        $fyForEQ = $fy ?: $currentFy;

        $eqUserFilter = null;
        if (in_array($ctx['type'], ['user','pc','client']) && !empty($ctx['user_id'])) {
            $eqUserFilter = [$ctx['user_id']];
        } elseif ($ctx['type'] === 'team') {
            $eqUserFilter = $this->teamUserIds($ctx['team_id']);
        }

        $extraRows = DB::table('extra_quota_assignments as eq')
            ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','eq.profit_center_code'))
            ->when(in_array($ctx['type'], ['pc','client']), fn($q)=>$q->where('eq.profit_center_code', $ctx['pc_code']))
            ->where('eq.fiscal_year', $fyForEQ)
            ->where('eq.is_published', 1)
            ->when(is_array($eqUserFilter) && !empty($eqUserFilter), fn($q)=>$q->whereIn('eq.user_id', $eqUserFilter))
            ->select(
                'eq.profit_center_code',
                DB::raw('SUM(eq.volume) as units'),
                DB::raw('SUM(eq.volume * COALESCE(uc.factor_to_m3,1)) as m3'),
                DB::raw('SUM(eq.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as euro')
            )
            ->groupBy('eq.profit_center_code')
            ->get();

        $budgetMap = [];
        foreach ($budgetRows as $r) {
            $code = $r->profit_center_code;
            $budgetMap[$code] = [
                'profit_center_code' => $code,
                'units' => (float)$r->units,
                'm3'    => (float)$r->m3,
                'euro'  => (float)$r->euro,
            ];
        }
        foreach ($extraRows as $r) {
            $code = $r->profit_center_code;
            if (!isset($budgetMap[$code])) {
                $budgetMap[$code] = [
                    'profit_center_code' => $code,
                    'units' => 0.0, 'm3' => 0.0, 'euro'  => 0.0
                ];
            }
            $budgetMap[$code]['units'] += (float)$r->units;
            $budgetMap[$code]['m3']    += (float)$r->m3;
            $budgetMap[$code]['euro']  += (float)$r->euro;
        }
        $budgetWithExtraRows = collect(array_values($budgetMap));

        $extraTotals = [
            'm3'   => $sum($extraRows, 'm3'),
            'euro' => $sum($extraRows, 'euro')
        ] + ($includeUnits ? ['units' => $sum($extraRows, 'units')] : []);

        return response()->json([
            'level'   => $ctx,
            'period'  => ['fiscal_year' => $fy, 'from' => $from, 'to' => $to],
            'sales'   => [
                'by_pc' => $this->fmtRows($salesRows, ['units','m3','euro']),
                'total' => ['m3' => $this->fmt($sum($salesRows, 'm3')), 'euro' => $this->fmt($sum($salesRows, 'euro'))] + ($includeUnits ? ['units' => $this->fmt($sum($salesRows, 'units'))] : []),
            ],
            'budgets' => [
                'by_pc' => $this->fmtRows($budgetWithExtraRows, ['units','m3','euro']),
                'total' => ['m3' => $this->fmt($sum($budgetWithExtraRows, 'm3')), 'euro' => $this->fmt($sum($budgetWithExtraRows, 'euro'))] + ($includeUnits ? ['units' => $this->fmt($sum($budgetWithExtraRows, 'units'))] : []),
            ],
            'forecasts' => [
                'by_pc' => $this->fmtRows($forecastRows, ['units','m3','euro']),
                'total' => ['m3' => $this->fmt($sum($forecastRows, 'm3')), 'euro' => $this->fmt($sum($forecastRows, 'euro'))] + ($includeUnits ? ['units' => $this->fmt($sum($forecastRows, 'units'))] : []),
            ],
            'extra_quotas' => [
                'by_pc' => $this->fmtRows($extraRows, ['units','m3','euro']),
                'total'   => array_map(fn($v) => $this->fmt($v), $extraTotals),
            ],
        ]);
    }

    public function debugTeamUsers(Request $request){
        $teamId = (int)$request->query('team_id');
        $team = DB::table('teams')->where('id', $teamId)->first(['id','manager_user_id']);

        $assignees = DB::table('assignments')
            ->where('assignments.team_id', $teamId)
            ->whereNotNull('assignments.user_id')
            ->join('team_members', function($j){
                $j->on('team_members.team_id','=','assignments.team_id')
                  ->on('team_members.user_id','=','assignments.user_id');
            })
            ->whereNull('team_members.deleted_at')
            ->distinct()
            ->pluck('assignments.user_id');

        $members = DB::table('team_members')
            ->where('team_members.team_id', $teamId)
            ->whereNull('team_members.deleted_at')
            ->select('team_members.user_id','team_members.role')
            ->get();

        return response()->json([
            'team_id' => $teamId,
            'manager_user_id' => $team->manager_user_id ?? null,
            'assignees_user_ids' => $assignees,
            'team_members' => $members,
            'computed_user_ids' => array_values(array_unique(array_merge(
                $assignees->map(fn($u)=>(int)$u)->toArray(),
                $members->pluck('user_id')->map(fn($u)=>(int)$u)->toArray(),
                [$team->manager_user_id ?? 0]
            )))
        ]);
    }

    /**
     * Monthly PC view (includes extra-quota "used" with latest version per group-month).
     */
public function pcMonthly(Request $request){
    $pc = trim((string)$request->query('profit_center_code', ''));
    if ($pc === '') {
        return response()->json(['error' => 'profit_center_code ist erforderlich'], 422);
    }

    $fyStart = (int)$request->query('fiscal_year', (int)date('Y'));
    if ($fyStart < 2024) $fyStart = 2024;

    $monthsOrdered = [4,5,6,7,8,9,10,11,12,1,2,3];
    $labels = [];
    foreach ($monthsOrdered as $m) {
        $y = ($m >= 4) ? $fyStart : ($fyStart + 1);
        $labels[] = sprintf('%04d-%02d', $y, $m);
    }
    $fyLabel = 'WJ ' . $fyStart . '/' . substr((string)($fyStart+1), -2);

    $cpcIds = DB::table('client_profit_centers')
        ->where('client_profit_centers.profit_center_code', $pc)
        ->pluck('id')
        ->all();

    if (empty($cpcIds)) {
        $zeros = array_fill(0, 12, 0.0);
        return response()->json([
            'profit_center_code' => $pc,
            'fy_start' => $fyStart,
            'fy_label' => $fyLabel,
            'months' => $labels,
            'sales'     => ['units'=>$this->fmtArr($zeros),'m3'=>$this->fmtArr($zeros),'total'=>['units'=>$this->fmt(0),'m3'=>$this->fmt(0)]],
            'budgets'   => ['units'=>$this->fmtArr($zeros),'m3'=>$this->fmtArr($zeros),'total'=>['units'=>$this->fmt(0),'m3'=>$this->fmt(0)]],
            'forecasts' => ['units'=>$this->fmtArr($zeros),'m3'=>$this->fmtArr($zeros),'total'=>['units'=>$this->fmt(0),'m3'=>$this->fmt(0)]],
            'extra_quota' => ['by_user'=>[], 'total'=>['units'=>$this->fmt(0),'m3'=>$this->fmt(0)]],
        ]);
    }

    $applyFY = function($q, $tbl) use ($fyStart) {
        $q->where(function($w) use ($tbl, $fyStart) {
            $w->where(function($a) use ($tbl, $fyStart) {
                $a->where($tbl.'.fiscal_year', $fyStart)
                  ->whereBetween($tbl.'.month', [4,12]);
            })->orWhere(function($b) use ($tbl, $fyStart) {
                $b->where($tbl.'.fiscal_year', $fyStart+1)
                  ->whereBetween($tbl.'.month', [1,3]);
            });
        });
    };

    $fyIndex = function(int $rowFy, int $month) use ($fyStart): int {
        return ($rowFy === $fyStart) ? max(0,min(11,$month-4)) : max(0,min(11,8+$month));
    };
    $make12 = fn() => array_fill(0, 12, 0.0);

    $ucAgg = DB::table('unit_conversions')
        ->select(
            'profit_center_code',
            DB::raw('MAX(factor_to_m3) as factor_to_m3'),
            DB::raw('MAX(factor_to_euro) as factor_to_euro')
        )->groupBy('profit_center_code');

    // SALES
    $salesQ = DB::table('sales')
        ->join('client_profit_centers', 'client_profit_centers.id', '=', 'sales.client_profit_center_id')
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code'))
        ->whereIn('sales.client_profit_center_id', $cpcIds);
    $applyFY($salesQ, 'sales');

    $salesU=$make12(); $salesM=$make12();
    foreach ($salesQ->select(
        'sales.fiscal_year','sales.month',
        DB::raw('SUM(sales.volume) as units'),
        DB::raw('SUM(sales.volume * COALESCE(uc.factor_to_m3,1)) as m3')
    )->groupBy('sales.fiscal_year','sales.month')->get() as $r) {
        $i = $fyIndex((int)$r->fiscal_year, (int)$r->month);
        $salesU[$i] = (float)$r->units;
        $salesM[$i] = (float)$r->m3;
    }

    // BUDGETS
    $budQ = DB::table('budgets')
        ->join('client_profit_centers', 'client_profit_centers.id', '=', 'budgets.client_profit_center_id')
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code'))
        ->whereIn('budgets.client_profit_center_id', $cpcIds);
    $applyFY($budQ, 'budgets');
    $budU=$make12(); $budM=$make12();
    foreach ($budQ->select(
        'budgets.fiscal_year','budgets.month',
        DB::raw('SUM(budgets.volume) as units'),
        DB::raw('SUM(budgets.volume * COALESCE(uc.factor_to_m3,1)) as m3')
    )->groupBy('budgets.fiscal_year','budgets.month')->get() as $r) {
        $i = $fyIndex((int)$r->fiscal_year, (int)$r->month);
        $budU[$i] = (float)$r->units;
        $budM[$i] = (float)$r->m3;
    }

    // FORECASTS (latest per author)
    $fcBase = DB::table('forecasts')
        ->whereIn('forecasts.client_profit_center_id', $cpcIds)
        ->select(
            'forecasts.client_profit_center_id',
            'forecasts.fiscal_year',
            'forecasts.month',
            'forecasts.user_id',
            DB::raw('MAX(forecasts.version) as max_version')
        )
        ->groupBy('forecasts.client_profit_center_id','forecasts.fiscal_year','forecasts.month','forecasts.user_id');

    $fcQ = DB::table('forecasts')
        ->joinSub($fcBase, 'lv', function($j){
            $j->on('forecasts.client_profit_center_id','=','lv.client_profit_center_id')
              ->on('forecasts.fiscal_year','=','lv.fiscal_year')
              ->on('forecasts.month','=','lv.month')
              ->on('forecasts.user_id','=','lv.user_id')
              ->on('forecasts.version','=','lv.max_version');
        })
        ->join('client_profit_centers', 'client_profit_centers.id', '=', 'forecasts.client_profit_center_id')
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code'))
        ->whereIn('forecasts.client_profit_center_id', $cpcIds);
    $applyFY($fcQ, 'forecasts');

    $fcU=$make12(); $fcM=$make12();
    foreach ($fcQ->select(
        'forecasts.fiscal_year','forecasts.month',
        DB::raw('SUM(forecasts.volume) as units'),
        DB::raw('SUM(forecasts.volume * COALESCE(uc.factor_to_m3,1)) as m3')
    )->groupBy('forecasts.fiscal_year','forecasts.month')->get() as $r) {
        $i = $fyIndex((int)$r->fiscal_year, (int)$r->month);
        $fcU[$i] = (float)$r->units;
        $fcM[$i] = (float)$r->m3;
    }

    // === EXTRA QUOTA (remaining = assigned - won) ===
    // ASSIGNED
    $eqAssignedQ = DB::table('extra_quota_assignments as eq')
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','eq.profit_center_code'))
        ->where('eq.fiscal_year', $fyStart)->where('eq.is_published', 1)
        ->where('eq.profit_center_code', $pc)
        ->selectRaw('
            COALESCE(SUM(eq.volume),0) as units,
            COALESCE(SUM(eq.volume * COALESCE(uc.factor_to_m3,1)),0) as m3,
            COALESCE(SUM(eq.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)),0) as euro
        ')
        ->first();
    $assignedU = (float)($eqAssignedQ->units ?? 0.0);
    $assignedM = (float)($eqAssignedQ->m3 ?? 0.0);
    $assignedE = (float)($eqAssignedQ->euro ?? 0.0);

    // WON converted
    $wonConvQ = DB::table('sales_opportunities as s')
        ->where('s.status','won')
        ->where('s.profit_center_code', $pc)
        ->join('extra_quota_budgets as b', function($j){
            $j->on('b.opportunity_group_id','=','s.opportunity_group_id')
              ->on('b.version','=','s.version');
        })
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','s.profit_center_code'));
    $applyFY($wonConvQ, 'b');
    $wonRow = $wonConvQ->selectRaw('
        COALESCE(SUM(b.volume),0) as units,
        COALESCE(SUM(b.volume * COALESCE(uc.factor_to_m3,1)),0) as m3,
        COALESCE(SUM(b.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)),0) as euro
    ')->first();

    $wonU = (float)($wonRow->units ?? 0.0);
    $wonM = (float)($wonRow->m3 ?? 0.0);
    $wonE = (float)($wonRow->euro ?? 0.0);

    // REMAINING
    $extraU = max(0.0, $assignedU - $wonU);
    $extraM = max(0.0, $assignedM - $wonM);
    $extraE = max(0.0, $assignedE - $wonE);

    // Distribute remaining into monthly budget arrays
    $applyExtra = function(array $base, float $extra) {
        $total = array_sum($base);
        if ($extra == 0.0) return $base;
        if ($total <= 0) {
            $per = $extra / 12.0;
            foreach ($base as $i => $v) $base[$i] = $v + $per;
            return $base;
        }
        foreach ($base as $i => $v) {
            $frac = ($v <= 0) ? 0.0 : ($v / $total);
            $base[$i] = $v + ($extra * $frac);
        }
        return $base;
    };
    $budUAdj = $applyExtra($budU, $extraU);
    $budMAdj = $applyExtra($budM, $extraM);

    // USED (OPEN) as-of (opcional €)
    $now = new \DateTimeImmutable('now');
    $nowY = (int)$now->format('Y'); $nowM = (int)$now->format('n');
    $currentFYStart = ($nowM >= 4) ? $nowY : ($nowY - 1);
    $asOfDate = ($currentFYStart === $fyStart)
        ? $now->modify('first day of this month')->modify('-1 day')->setTime(23,59,59)
        : (new \DateTimeImmutable(sprintf('%04d-03-31 23:59:59', $fyStart + 1)));
    $asOfStr = $asOfDate->format('Y-m-d H:i:s');

    $eqfRow = DB::table('extra_quota_forecasts as eqf')
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','eqf.profit_center_code'))
        ->where('eqf.fiscal_year', $fyStart)
        ->where('eqf.profit_center_code', $pc)
        ->where('eqf.created_at', '<=', $asOfStr)
        ->selectRaw('
            COALESCE(SUM(eqf.volume),0) as units,
            COALESCE(SUM(eqf.volume * COALESCE(uc.factor_to_m3,1)),0) as m3,
            COALESCE(SUM(eqf.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)),0) as euro
        ')
        ->first();

    $eqfU = (float)($eqfRow->units ?? 0.0);
    $eqfM = (float)($eqfRow->m3 ?? 0.0);

    $extraRemU = max(0.0, $extraU - $eqfU);
    $extraRemM = max(0.0, $extraM - $eqfM);

    $fmt = fn($n) => number_format((float)$n, 0, ',', '.');
    $make12fmt = fn($arr) => array_map($fmt, $arr);

    return response()->json([
        'profit_center_code' => $pc,
        'fy_start' => $fyStart,
        'fy_label' => $fyLabel,
        'months'   => $labels,
        'raw' => [
            'monthly' => [
                'sales'    => ['units'=>$salesU,  'm3'=>$salesM,  'euro'=>$make12()],
                'budgets'  => ['units'=>$budUAdj, 'm3'=>$budMAdj, 'euro'=>$make12()],
                'forecast' => ['units'=>$fcU,     'm3'=>$fcM,     'euro'=>$make12()],
            ],
        ],
        'extra_quota' => [
            // allocated = remaining (already converted correctly to €)
            'allocated' => ['units'=>$fmt($extraU), 'm3'=>$fmt($extraM), 'euro'=>$fmt($extraE)],
            'used'      => ['units'=>$fmt($eqfU),   'm3'=>$fmt($eqfM),   'euro'=>$fmt(0)],
            'remaining' => ['units'=>$fmt($extraRemU), 'm3'=>$fmt($extraRemM), 'euro'=>$fmt(0)],
        ],
    ]);
}

public function pcOverview(Request $request){
    $pc = trim((string)$request->query('profit_center_code', ''));
    if ($pc === '') {
        return response()->json(['error' => 'profit_center_code ist erforderlich'], 422);
    }

    $fyStart = (int)$request->query('fiscal_year', (int)date('Y'));
    if ($fyStart < 2024) $fyStart = 2024;

    $asOfYm = trim((string)$request->query('as_of', ''));

    $endOfMonth = function(int $Y, int $m): \DateTimeImmutable {
        return (new \DateTimeImmutable(sprintf('%04d-%02d-01', $Y, $m)))
            ->modify('last day of this month')->setTime(23,59,59);
    };
    $now = new \DateTimeImmutable('now');
    $nowY = (int)$now->format('Y'); $nowM = (int)$now->format('n');
    $currentFYStart = ($nowM >= 4) ? $nowY : ($nowY - 1);
    $inSameFY = ($currentFYStart === $fyStart);

    if (preg_match('/^\d{4}-\d{2}$/', $asOfYm)) {
        [$asY, $asM] = array_map('intval', explode('-', $asOfYm));
        $asOfDate = $endOfMonth($asY, $asM);
    } else {
        $asOfDate = $inSameFY
            ? $now->modify('first day of this month')->modify('-1 day')->setTime(23,59,59)
            : (new \DateTimeImmutable(sprintf('%04d-03-31 23:59:59', $fyStart + 1)));
    }
    $asOfStr = $asOfDate->format('Y-m-d H:i:s');

    $monthsOrdered = [4,5,6,7,8,9,10,11,12,1,2,3];
    $labels = [];
    foreach ($monthsOrdered as $m) {
        $y = ($m >= 4) ? $fyStart : ($fyStart + 1);
        $labels[] = sprintf('%04d-%02d', $y, $m);
    }
    $fyLabel = 'WJ ' . $fyStart . '/' . substr((string)($fyStart+1), -2);

    $fyIndex = function(int $rowFy, int $month) use ($fyStart): int {
        return ($rowFy === $fyStart) ? max(0,min(11,$month-4)) : max(0,min(11,8+$month));
    };
    $make12 = fn() => array_fill(0, 12, 0.0);

    $asY = (int)$asOfDate->format('Y'); $asM = (int)$asOfDate->format('n');
    $lastIdx = $fyIndex($asY, $asM);

    $cpcIds = DB::table('client_profit_centers')
        ->where('client_profit_centers.profit_center_code', $pc)
        ->pluck('id')
        ->all();

    if (empty($cpcIds)) {
        $zeros = $make12();
        $fmt = fn($n) => number_format((float)$n, 0, ',', '.');
        return response()->json([
            'profit_center_code'   => $pc,
            'fy_start'             => $fyStart,
            'fy_label'             => $fyLabel,
            'months'               => $labels,
            'last_complete_index'  => $lastIdx,
            'as_of'                => $asOfDate->format('Y-m'),
            'ytd' => [
                'sales'    => ['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                'budgets'  => ['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                'forecast' => ['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                'attainment' => [
                    'vs_budget'   => ['pct'=>0.0, 'trend'=>[]],
                    'vs_forecast' => ['pct'=>0.0, 'trend'=>[]],
                ],
            ],
            'future_6m' => [
                'labels'   => [],
                'budget'   => ['units'=>[],'m3'=>[],'euro'=>[]],
                'forecast' => ['units'=>[],'m3'=>[],'euro'=>[]],
                'totals'   => [
                    'budget'   => ['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                    'forecast' => ['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                ],
            ],
            'extra_quota' => [
                'allocated'=>['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                'used'     =>['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                'remaining'=>['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
            ],
            'raw' => [
                'monthly' => [
                    'sales'    => ['units'=>$zeros,'m3'=>$zeros,'euro'=>$zeros],
                    'budgets'  => ['units'=>$zeros,'m3'=>$zeros,'euro'=>$zeros],
                    'forecast' => ['units'=>$zeros,'m3'=>$zeros,'euro'=>$zeros],
                ],
            ],
        ]);
    }

    $ucAgg = DB::table('unit_conversions')
        ->select(
            'profit_center_code',
            DB::raw('MAX(factor_to_m3) as factor_to_m3'),
            DB::raw('MAX(factor_to_euro) as factor_to_euro')
        )->groupBy('profit_center_code');

    $applyFY = function($q, $tbl) use ($fyStart) {
        $q->where(function($w) use ($tbl, $fyStart) {
            $w->where(function($a) use ($tbl, $fyStart) {
                $a->where($tbl.'.fiscal_year', $fyStart)->whereBetween($tbl.'.month', [4,12]);
            })->orWhere(function($b) use ($tbl, $fyStart) {
                $b->where($tbl.'.fiscal_year', $fyStart+1)->whereBetween($tbl.'.month', [1,3]);
            });
        });
    };

    // SALES
    $salesQ = DB::table('sales')
        ->join('client_profit_centers', 'client_profit_centers.id', '=', 'sales.client_profit_center_id')
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code'))
        ->whereIn('sales.client_profit_center_id', $cpcIds);
    $applyFY($salesQ, 'sales');

    $salesU=$make12(); $salesM=$make12(); $salesE=$make12();
    foreach ($salesQ->select(
        'sales.fiscal_year','sales.month',
        DB::raw('SUM(sales.volume) as units'),
        DB::raw('SUM(sales.volume * COALESCE(uc.factor_to_m3,1)) as m3'),
        DB::raw('SUM(sales.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as euro')
    )->groupBy('sales.fiscal_year','sales.month')->get() as $r) {
        $i = $fyIndex((int)$r->fiscal_year, (int)$r->month);
        $salesU[$i] = (float)$r->units;
        $salesM[$i] = (float)$r->m3;
        $salesE[$i] = (float)$r->euro;
    }

    // BUDGETS
    $budQ = DB::table('budgets')
        ->join('client_profit_centers', 'client_profit_centers.id', '=', 'budgets.client_profit_center_id')
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code'))
        ->whereIn('budgets.client_profit_center_id', $cpcIds);
    $applyFY($budQ, 'budgets');

    $budU=$make12(); $budM=$make12(); $budE=$make12();
    foreach ($budQ->select(
        'budgets.fiscal_year','budgets.month',
        DB::raw('SUM(budgets.volume) as units'),
        DB::raw('SUM(budgets.volume * COALESCE(uc.factor_to_m3,1)) as m3'),
        DB::raw('SUM(budgets.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as euro')
    )->groupBy('budgets.fiscal_year','budgets.month')->get() as $r) {
        $i = $fyIndex((int)$r->fiscal_year, (int)$r->month);
        $budU[$i] = (float)$r->units;
        $budM[$i] = (float)$r->m3;
        $budE[$i] = (float)$r->euro;
    }

    // === EXTRA QUOTA (remaining = assigned - won) ===
    // ASSIGNED
    $eqAssignedQ = DB::table('extra_quota_assignments as eq')
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','eq.profit_center_code'))
        ->where('eq.fiscal_year', $fyStart)->where('eq.is_published', 1)
        ->where('eq.profit_center_code', $pc)
        ->selectRaw('
            COALESCE(SUM(eq.volume),0) as units,
            COALESCE(SUM(eq.volume * COALESCE(uc.factor_to_m3,1)),0) as m3,
            COALESCE(SUM(eq.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)),0) as euro
        ')
        ->first();
    $assignedU = (float)($eqAssignedQ->units ?? 0.0);
    $assignedM = (float)($eqAssignedQ->m3 ?? 0.0);
    $assignedE = (float)($eqAssignedQ->euro ?? 0.0);

    // WON converted
    $wonConvQ = DB::table('sales_opportunities as s')
        ->where('s.status','won')
        ->where('s.profit_center_code', $pc)
        ->join('extra_quota_budgets as b', function($j){
            $j->on('b.opportunity_group_id','=','s.opportunity_group_id')
              ->on('b.version','=','s.version');
        })
        ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','s.profit_center_code'));
    $applyFY($wonConvQ, 'b');

    $wonRow = $wonConvQ->selectRaw('
        COALESCE(SUM(b.volume),0) as units,
        COALESCE(SUM(b.volume * COALESCE(uc.factor_to_m3,1)),0) as m3,
        COALESCE(SUM(b.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)),0) as euro
    ')->first();

    $wonU = (float)($wonRow->units ?? 0.0);
    $wonM = (float)($wonRow->m3 ?? 0.0);
    $wonE = (float)($wonRow->euro ?? 0.0);

    // REMAINING
    $extraU = max(0.0, $assignedU - $wonU);
    $extraM = max(0.0, $assignedM - $wonM);
    $extraE = max(0.0, $assignedE - $wonE);

    // Distribute remaining into monthly budget arrays
    $applyExtra = function(array $base, float $extra) {
        $total = array_sum($base);
        if ($extra == 0.0) return $base;
        if ($total <= 0) {
            $per = $extra / 12.0;
            foreach ($base as $i => $v) $base[$i] = $v + $per;
            return $base;
        }
        foreach ($base as $i => $v) {
            $frac = ($v <= 0) ? 0.0 : ($v / $total);
            $base[$i] = $v + ($extra * $frac);
        }
        return $base;
    };
    $budUAdj = $applyExtra($budU, $extraU);
    $budMAdj = $applyExtra($budM, $extraM);
    $budEAdj = $applyExtra($budE, $extraE);

    // Forecasts monthly (as-of fallback) – mantené igual tu lógica
    $buildForecast = function(bool $useAsOf) use ($cpcIds, $ucAgg, $applyFY, $fyStart, $fyIndex, $make12, $asOfStr) {
        $fcU = $make12(); $fcM = $make12(); $fcE = $make12();

        $fcBase = DB::table('forecasts')
            ->whereIn('forecasts.client_profit_center_id', $cpcIds)
            ->when($useAsOf, fn($q)=>$q->where('forecasts.created_at', '<=', $asOfStr))
            ->select(
                'forecasts.client_profit_center_id',
                'forecasts.fiscal_year',
                'forecasts.month',
                'forecasts.user_id',
                DB::raw('MAX(forecasts.version) as max_version')
            )
            ->groupBy('forecasts.client_profit_center_id','forecasts.fiscal_year','forecasts.month','forecasts.user_id');

        $fcQ = DB::table('forecasts')
            ->joinSub($fcBase, 'lv', function($j){
                $j->on('forecasts.client_profit_center_id','=','lv.client_profit_center_id')
                  ->on('forecasts.fiscal_year','=','lv.fiscal_year')
                  ->on('forecasts.month','=','lv.month')
                  ->on('forecasts.user_id','=','lv.user_id')
                  ->on('forecasts.version','=','lv.max_version');
            })
            ->join('client_profit_centers', 'client_profit_centers.id', '=', 'forecasts.client_profit_center_id')
            ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','client_profit_centers.profit_center_code'));
        $applyFY($fcQ, 'forecasts');

        $rows = $fcQ->select(
                'forecasts.fiscal_year','forecasts.month',
                DB::raw('SUM(forecasts.volume) as units'),
                DB::raw('SUM(forecasts.volume * COALESCE(uc.factor_to_m3,1)) as m3'),
                DB::raw('SUM(forecasts.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as euro')
            )->groupBy('forecasts.fiscal_year','forecasts.month')
            ->get();

        $count = 0;
        foreach ($rows as $r) {
            $i = $fyIndex((int)$r->fiscal_year, (int)$r->month);
            $fcU[$i] = (float)$r->units;
            $fcM[$i] = (float)$r->m3;
            $fcE[$i] = (float)$r->euro;
            $count++;
        }
        return [$fcU,$fcM,$fcE,$count];
    };

    [$fcU,$fcM,$fcE,$fcCount] = $buildForecast(true);
    if ($fcCount === 0) {
        [$fcU,$fcM,$fcE,$fcCount] = $buildForecast(false);
    }

    $fmt    = fn($n) => number_format((float)$n, 0, ',', '.');
    $fmtArr = fn($arr) => array_map($fmt, $arr);
    $sumSlice = function(array $arr, int $toIdx) {
        $s = 0.0; for ($i=0; $i<=max(0,$toIdx); $i++) $s += $arr[$i] ?? 0.0; return $s;
    };
    $ytdSales = ['units'=>$sumSlice($thisArr=$make12(),0)]; // placeholder no usado aquí

    return response()->json([
        'profit_center_code'   => $pc,
        'fy_start'             => $fyStart,
        'fy_label'             => $fyLabel,
        'months'               => $labels,
        'last_complete_index'  => $lastIdx,
        'as_of'                => $asOfDate->format('Y-m'),
        'raw' => [
            'monthly' => [
                'sales'    => ['units'=>$salesU,  'm3'=>$salesM,  'euro'=>$salesE],
                'budgets'  => ['units'=>$budUAdj, 'm3'=>$budMAdj, 'euro'=>$budEAdj],
                'forecast' => ['units'=>$fcU,     'm3'=>$fcM,     'euro'=>$fcE],
            ],
        ],
        'extra_quota' => [
            // allocated = remaining (€ corregido con fm3*feuro)
            'allocated' => ['units'=>$fmt($extraU),  'm3'=>$fmt($extraM),  'euro'=>$fmt($extraE)],
            'used'      => ['units'=>$fmt(0),        'm3'=>$fmt(0),        'euro'=>$fmt(0)],
            'remaining' => ['units'=>$fmt($extraU),  'm3'=>$fmt($extraM),  'euro'=>$fmt($extraE)],
        ],
    ]);
}

    /**
     * Returns profit center list.
     */
    public function pcList(Request $request){
        $rows = DB::table('profit_centers')
        ->select(
            'profit_centers.profit_center_code as code',
            DB::raw('COALESCE(profit_centers.profit_center_name, profit_centers.profit_center_code) as name')
        )
        ->orderBy('name')
        ->get();

        return response()->json($rows);
    }
}