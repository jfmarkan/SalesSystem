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
        // Fetch all teams (id, name, manager_user_id).
        $teams = DB::table('teams')
            ->select('id', 'name', 'manager_user_id')
            ->orderBy('name')
            ->get();

        if ($teams->isEmpty()) return [];

        $teamIds = $teams->pluck('id')->all();

        // Fetch ACTIVE team members only (pivot soft-delete respected).
        $teamMembers = DB::table('team_members')
            ->whereIn('team_members.team_id', $teamIds)
            ->whereNull('team_members.deleted_at') // Active in this team
            ->select('team_members.team_id', 'team_members.user_id', 'team_members.role')
            ->get();

        // Build manager map (team_id => manager_user_id).
        $managerPerTeam = [];
        foreach ($teams as $t) {
            if ($t->manager_user_id) {
                $managerPerTeam[$t->id] = (int)$t->manager_user_id;
            }
        }

        // Build active member map: [team_id][user_id] => role('manager'|'seller')
        $activeMembers = [];
        foreach ($teamMembers as $m) {
            $role = strtolower((string)$m->role) === 'manager' ? 'manager' : 'seller';
            $activeMembers[$m->team_id][(int)$m->user_id] = $role;
        }

        // Build initial usersByTeam from manager + active members only.
        $usersByTeam = [];    // [team_id][user_id] => role
        $userIdsAll  = [];    // list of user ids used for display labels
        foreach ($teams as $t) {
            $tid = (int)$t->id;

            // Include manager (always visible as team leader in UI).
            if (!empty($managerPerTeam[$tid])) {
                $mgrId = $managerPerTeam[$tid];
                $usersByTeam[$tid][$mgrId] = 'manager';
                $userIdsAll[] = $mgrId;
            }

            // Include active members.
            if (!empty($activeMembers[$tid])) {
                foreach ($activeMembers[$tid] as $uid => $role) {
                    $usersByTeam[$tid][$uid] = $role;
                    $userIdsAll[] = (int)$uid;
                }
            }
        }

        // Fetch assignments for these teams (NO user filter here; we'll filter by active membership next).
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

        // Display names for the users we currently plan to show (from manager+active members only).
        $userIdsAll = array_values(array_unique($userIdsAll));
        $display = $this->fetchUsersDisplay($userIdsAll);

        // Build PC/Client map only for users who are active in the team (or are the manager).
        // Structure: [team_id][user_id][pc_code] => ['pc_name'=>..., 'clients'=>[], '_seen'=>[]]
        $map = [];
        foreach ($assignments as $r) {
            if (!$r->user_id) continue;

            $tid = (int)$r->team_id;
            $uid = (int)$r->user_id;

            // Only keep assignment if user is currently visible in that team:
            // either active member (pivot not deleted) or the team manager.
            $isVisibleInTeam = isset($usersByTeam[$tid][$uid]);
            if (!$isVisibleInTeam) continue;

            $pcCode = (string)$r->profit_center_code;
            $pcName = (string)($r->profit_center_name ?? $pcCode);
            $cgn    = $r->client_group_number ? (string)$r->client_group_number : null;
            $cname  = $r->client_name ? (string)$r->client_name : null;

            $map[$tid]              = $map[$tid]              ?? [];
            $map[$tid][$uid]        = $map[$tid][$uid]        ?? [];
            $map[$tid][$uid][$pcCode] = $map[$tid][$uid][$pcCode] ?? ['pc_name' => $pcName, 'clients' => [], '_seen' => []];

            if ($cgn && !isset($map[$tid][$uid][$pcCode]['_seen'][$cgn])) {
                $map[$tid][$uid][$pcCode]['clients'][] = [
                    'client_group_number' => $cgn,
                    'client_name'         => $cname ?? $cgn
                ];
                $map[$tid][$uid][$pcCode]['_seen'][$cgn] = true;
            }
        }

        // Sort clients alphabetically and PCs by key for consistency.
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

        // Build final output tree nodes.
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

                // UI suffix in German
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
     * No filtering by users.deleted_at; visibility is controlled at the team_members pivot.
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

    /**
     * Formats a number with German thousand separator and no decimals.
     */
    private function fmt($n): string{
        return number_format((float)$n, 0, ',', '.');
    }

    /**
     * Formats an array of numbers using fmt().
     */
    private function fmtArr(array $nums): array{
        return array_map(fn($v) => $this->fmt($v), $nums);
    }

    /**
     * Formats rows numeric fields using fmt().
     */
    private function fmtRows($rows, array $fields = ['units','m3','euro']){
        $out = [];
        foreach ($rows as $r) {
            $row = (array)$r;
            foreach ($fields as $f) {
                if (array_key_exists($f, $row)) {
                    $row[$f] = $this->fmt($row[$f]);
                }
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Returns active user ids for a team:
     * - includes the manager (teams.manager_user_id)
     * - includes members where team_members.deleted_at IS NULL
     * - includes assignees ONLY if they are active in team_members (deleted_at IS NULL)
     */
    private function teamUserIds(int $teamId): array{
        $ids = [];

        // Manager (always visible)
        $mgr = DB::table('teams')->where('id', $teamId)->value('manager_user_id');
        if ($mgr) $ids[] = (int)$mgr;

        // Active members (pivot not soft-deleted)
        $members = DB::table('team_members')
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->pluck('user_id')->map(fn($u)=>(int)$u)->all();

        // Assignees who are active members in this team
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

        $ids = array_values(array_unique(array_filter(array_merge($ids, $members, $assignees))));
        return $ids;
    }

    /**
     * Returns monthly series for the given node context within a fiscal year.
     * UI labels use German (fy_label uses "WJ").
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
        // UI German fiscal year label
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

        // Helper to apply fiscal year window (Apr-Mar).
        $applyFY = function($q, $tbl) use ($fyStart) {
            $q->where(function($w) use ($tbl, $fyStart) {
                $w->where(function($a) use ($tbl, $fyStart) {
                    $a->where($tbl.'.fiscal_year', $fyStart)->whereBetween($tbl.'.month', [4,12]);
                })->orWhere(function($b) use ($tbl, $fyStart) {
                    $b->where($tbl.'.fiscal_year', $fyStart+1)->whereBetween($tbl.'.month', [1,3]);
                });
            });
        };

        // Index within FY array [0..11]
        $fyIndex = fn(int $rowFy, int $month) => ($rowFy === $fyStart) ? max(0, min(11, $month - 4)) : max(0, min(11, 8 + $month));
        $make12 = fn() => array_fill(0, 12, 0.0);

        // Latest unit conversion per profit center
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
        $applyFY($salesQ, 'sales');

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
        $applyFY($budQ, 'budgets');

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
        $applyFY($fcQ, 'forecasts');

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

        // Extra quota selection scope
        $eqUserIds = null;
        if (in_array($ctx['type'], ['user','pc','client'])) {
            $eqUserIds = [$ctx['user_id']];
        } elseif ($ctx['type'] === 'team') {
            $eqUserIds = $this->teamUserIds($ctx['team_id']);
        }

        $eqQ = DB::table('extra_quota_assignments as eq')
            ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','eq.profit_center_code'))
            ->where('eq.fiscal_year', $fyStart)
            ->where('eq.is_published', 1)
            ->when($eqUserIds, fn($q)=>$q->whereIn('eq.user_id', $eqUserIds))
            ->when(in_array($ctx['type'], ['pc','client']), fn($q)=>$q->where('eq.profit_center_code', $ctx['pc_code']))
            ->selectRaw('SUM(eq.volume) as units, SUM(eq.volume * COALESCE(uc.factor_to_m3,1)) as m3, SUM(eq.volume * COALESCE(uc.factor_to_euro,0)) as euro')
            ->first();

        $extraUnits = (float)($eqQ->units ?? 0);
        $extraM3 = (float)($eqQ->m3 ?? 0);
        $extraEur = (float)($eqQ->euro ?? 0);

        // Distribute extra quota proportionally to existing monthly budget; or evenly if all zeros.
        $distribute = function($base, $extra) {
            $total = array_sum($base);
            if ($extra == 0) return $base;
            if ($total == 0) return array_map(fn() => $extra / 12, $base);
            foreach ($base as $i => $v) {
                $frac = ($v > 0) ? $v / $total : 0;
                $base[$i] += $extra * $frac;
            }
            return $base;
        };

        $applyExtraQuota = in_array($ctx['type'], ['company', 'team', 'user', 'pc']);

        $budgetsAdj    = $applyExtraQuota ? $distribute($budgets, $extraUnits)   : $budgets;
        $budgetM3Adj   = $applyExtraQuota ? $distribute($budgetM3, $extraM3)     : $budgetM3;
        $budgetEurAdj  = $applyExtraQuota ? $distribute($budgetEur, $extraEur)   : $budgetEur;

        return response()->json([
            'context' => $ctx,
            'fy_start' => $fyStart,
            'fy_label' => $fyLabel, // UI German label
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

    /**
     * Empty series scaffold when no CPCs found; UI labels in German.
     */
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
            'fy_label'          => 'WJ ' . $fyStart . '/' . substr((string)($fyStart+1), -2), // German
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
     * UI errors are in German.
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

        // Apply period either by FY (Apr-Mar) or by raw yyy-mm range.
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

        // Unit conversions
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

        // EXTRA QUOTAS
        $nowY = (int)date('Y'); $nowM = (int)date('n');
        $currentFy = ($nowM >= 4) ? $nowY : ($nowY - 1);
        $fyForEQ = $fy ?: $currentFy;

        // Limit EQ by users in scope (team active users, user, pc/client's user).
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

        // Merge budgets.by_pc + extraQuotas.by_pc
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
                    'units' => 0.0, 'm3' => 0.0, 'euro' => 0.0
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
                'total' => array_map(fn($v) => $this->fmt($v), $extraTotals),
            ],
        ]);
    }

    /**
     * Debug endpoint to inspect active users of a team.
     * Only active members (team_members.deleted_at IS NULL) are listed,
     * assignees are limited to active members, manager is returned raw.
     */
    public function debugTeamUsers(Request $request){
        $teamId = (int)$request->query('team_id');
        $team = DB::table('teams')->where('id', $teamId)->first(['id','manager_user_id']);

        // Assignees restricted to active members
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

        // Active members only
        $members = DB::table('team_members')
            ->where('team_members.team_id', $teamId)
            ->whereNull('team_members.deleted_at')
            ->select('team_members.user_id','team_members.role')
            ->get();

        // Computed union set (active members + active assignees + manager)
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
     * Monthly PC view for a single profit center.
     * UI errors and labels in German.
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
        $fyLabel = 'WJ ' . $fyStart . '/' . substr((string)($fyStart+1), -2); // German

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

        // FY window helper
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
        $salesRows = $salesQ->select(
                'sales.fiscal_year','sales.month',
                DB::raw('SUM(sales.volume) as units'),
                DB::raw('SUM(sales.volume * COALESCE(uc.factor_to_m3,1)) as m3')
            )->groupBy('sales.fiscal_year','sales.month')
             ->get();

        $salesU=$make12(); $salesM=$make12();
        foreach ($salesRows as $r) {
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
        $budRows = $budQ->select(
                'budgets.fiscal_year','budgets.month',
                DB::raw('SUM(budgets.volume) as units'),
                DB::raw('SUM(budgets.volume * COALESCE(uc.factor_to_m3,1)) as m3')
            )->groupBy('budgets.fiscal_year','budgets.month')
             ->get();

        $budU=$make12(); $budM=$make12();
        foreach ($budRows as $r) {
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

        $fcRows = $fcQ->select(
                'forecasts.fiscal_year','forecasts.month',
                DB::raw('SUM(forecasts.volume) as units'),
                DB::raw('SUM(forecasts.volume * COALESCE(uc.factor_to_m3,1)) as m3')
            )->groupBy('forecasts.fiscal_year','forecasts.month')
             ->get();

        $fcU=$make12(); $fcM=$make12();
        foreach ($fcRows as $r) {
            $i = $fyIndex((int)$r->fiscal_year, (int)$r->month);
            $fcU[$i] = (float)$r->units;
            $fcM[$i] = (float)$r->m3;
        }

        // EXTRA QUOTAS for selected FY at this PC (totals and by user)
        $extraByUser = DB::table('extra_quota_assignments as eq')
            ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','eq.profit_center_code'))
            ->where('eq.fiscal_year', $fyStart)
            ->where('eq.is_published', 1)
            ->where('eq.profit_center_code', $pc)
            ->select(
                'eq.user_id',
                DB::raw('SUM(eq.volume) as units'),
                DB::raw('SUM(eq.volume * COALESCE(uc.factor_to_m3,1)) as m3')
            )
            ->groupBy('eq.user_id')
            ->get();

        $extraTotalUnits = (float)$extraByUser->sum('units');
        $extraTotalM3    = (float)$extraByUser->sum('m3');

        // Apply extra quota proportionally to budget monthly values
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

        $budUAdj = $applyExtra($budU, $extraTotalUnits);
        $budMAdj = $applyExtra($budM, $extraTotalM3);

        $extraByUserFmt = [];
        foreach ($extraByUser as $row) {
            $extraByUserFmt[] = [
                'user_id' => (int)$row->user_id,
                'units'   => $this->fmt($row->units),
                'm3'      => $this->fmt($row->m3),
            ];
        }

        return response()->json([
            'profit_center_code' => $pc,
            'fy_start' => $fyStart,
            'fy_label' => $fyLabel, // German
            'months'   => $labels,
            'sales'     => ['units'=>$this->fmtArr($salesU), 'm3'=>$this->fmtArr($salesM), 'total'=>['units'=>$this->fmt(array_sum($salesU)),'m3'=>$this->fmt(array_sum($salesM))]],
            'budgets'   => ['units'=>$this->fmtArr($budUAdj),   'm3'=>$this->fmtArr($budMAdj),  'total'=>['units'=>$this->fmt(array_sum($budUAdj)),'m3'=>$this->fmt(array_sum($budMAdj))]],
            'forecasts' => ['units'=>$this->fmtArr($fcU),    'm3'=>$this->fmtArr($fcM),   'total'=>['units'=>$this->fmt(array_sum($fcU)),'m3'=>$this->fmt(array_sum($fcM))]],
            'extra_quotas' => [
                'by_user' => $extraByUserFmt,
                'total'   => ['units'=>$this->fmt($extraTotalUnits), 'm3'=>$this->fmt($extraTotalM3)],
            ],
        ]);
    }

    /**
     * Overview endpoint for a profit center (YTD and future).
     * UI labels and error texts in German.
     */
    public function pcOverview(Request $request){
        $pc = trim((string)$request->query('profit_center_code', ''));
        if ($pc === '') {
            return response()->json(['error' => 'profit_center_code ist erforderlich'], 422);
        }

        $fyStart = (int)$request->query('fiscal_year', (int)date('Y'));
        if ($fyStart < 2024) $fyStart = 2024;

        $asOfYm = trim((string)$request->query('as_of', ''));

        $endOfMonth = function(int $Y, int $m): \DateTimeImmutable {
            return (new \DateTimeImmutable(sprintf('%04d-%02d-01', $Y, $m)))->modify('last day of this month')->setTime(23,59,59);
        };

        $now = new \DateTimeImmutable('now');
        $nowY = (int)$now->format('Y');
        $nowM = (int)$now->format('n');
        $currentFYStart = ($nowM >= 4) ? $nowY : ($nowY - 1);
        $inSameFY = ($currentFYStart === $fyStart);

        if (preg_match('/^\d{4}-\d{2}$/', $asOfYm)) {
            [$asY, $asM] = array_map('intval', explode('-', $asOfYm));
            $asOfDate = $endOfMonth($asY, $asM);
        } else {
            if ($inSameFY) {
                $lastComplete = $now->modify('first day of this month')->modify('-1 day');
                $asOfDate = $lastComplete->setTime(23,59,59);
            } else {
                $asOfDate = (new \DateTimeImmutable(sprintf('%04d-03-31 23:59:59', $fyStart + 1)));
            }
        }
        $asOfStr = $asOfDate->format('Y-m-d H:i:s');

        $monthsOrdered = [4,5,6,7,8,9,10,11,12,1,2,3];
        $labels = [];
        foreach ($monthsOrdered as $m) {
            $y = ($m >= 4) ? $fyStart : ($fyStart + 1);
            $labels[] = sprintf('%04d-%02d', $y, $m);
        }
        $fyLabel = 'WJ ' . $fyStart . '/' . substr((string)($fyStart+1), -2); // German

        $fyIndex = function(int $rowFy, int $month) use ($fyStart): int {
            return ($rowFy === $fyStart) ? max(0,min(11,$month-4)) : max(0,min(11,8+$month));
        };
        $make12 = fn() => array_fill(0, 12, 0.0);

        $asY = (int)$asOfDate->format('Y');
        $asM = (int)$asOfDate->format('n');
        $lastIdx = $fyIndex($asY, $asM);

        $cpcIds = DB::table('client_profit_centers')
            ->where('client_profit_centers.profit_center_code', $pc)
            ->pluck('id')
            ->all();

        if (empty($cpcIds)) {
            $zeros = $make12();
            $fmt = fn($n) => number_format((float)$n, 0, ',', '.');
            return response()->json([
                'profit_center_code' => $pc,
                'fy_start' => $fyStart,
                'fy_label' => $fyLabel,
                'months' => $labels,
                'last_complete_index' => $lastIdx,
                'as_of' => $asOfDate->format('Y-m'),
                'ytd' => [
                    'sales'    => ['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                    'budgets'  => ['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                    'forecast' => ['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                    'attainment' => [
                        'vs_budget'  => ['pct'=>0.0, 'trend'=>[]],
                        'vs_forecast'=> ['pct'=>0.0, 'trend'=>[]],
                    ],
                ],
                'future_6m' => [
                    'labels' => [],
                    'budget' => ['units'=>[],'m3'=>[],'euro'=>[]],
                    'forecast' => ['units'=>[],'m3'=>[],'euro'=>[]],
                    'totals' => [
                        'budget' => ['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                        'forecast' => ['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
                    ],
                ],
                'chances' => ['total'=>0, 'by_user'=>[]],
                'extra_quota' => [
                    'allocated'=>['units'=>$fmt(0),'m3'=>$fmt(0),'euro'=>$fmt(0)],
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
                    $a->where($tbl.'.fiscal_year', $fyStart)
                    ->whereBetween($tbl.'.month', [4,12]);
                })->orWhere(function($b) use ($tbl, $fyStart) {
                    $b->where($tbl.'.fiscal_year', $fyStart+1)
                    ->whereBetween($tbl.'.month', [1,3]);
                });
            });
        };

        // SALES monthly
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

        // BUDGETS monthly
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

        // EXTRA QUOTAS (FY totals for this PC)
        $eqQ = DB::table('extra_quota_assignments as eq')
            ->leftJoinSub($ucAgg, 'uc', fn($j)=>$j->on('uc.profit_center_code','=','eq.profit_center_code'))
            ->where('eq.fiscal_year', $fyStart)
            ->where('eq.is_published', 1)
            ->where('eq.profit_center_code', $pc)
            ->selectRaw('SUM(eq.volume) as units, SUM(eq.volume * COALESCE(uc.factor_to_m3,1)) as m3, SUM(eq.volume * COALESCE(uc.factor_to_euro,0)) as euro')
            ->first();

        $extraU = (float)($eqQ->units ?? 0);
        $extraM = (float)($eqQ->m3 ?? 0);
        $extraE = (float)($eqQ->euro ?? 0);

        // Distribute extra quota
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

        // FORECASTS monthly (as-of fallback)
        $buildForecast = function(bool $useAsOf) use ($cpcIds, $ucAgg, $applyFY, $fyIndex, $make12, $asOfStr) {
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

        // YTD computations up to last complete month
        $sumSlice = function(array $arr, int $toIdx) {
            $s = 0.0; for ($i=0; $i<=max(0,$toIdx); $i++) $s += $arr[$i] ?? 0.0; return $s;
        };

        $ytdRaw = [
            'sales'    => ['units'=>$sumSlice($salesU, $lastIdx), 'm3'=>$sumSlice($salesM, $lastIdx), 'euro'=>$sumSlice($salesE, $lastIdx)],
            'budgets'  => ['units'=>$sumSlice($budUAdj, $lastIdx), 'm3'=>$sumSlice($budMAdj, $lastIdx), 'euro'=>$sumSlice($budEAdj, $lastIdx)],
            'forecast' => ['units'=>$sumSlice($fcU, $lastIdx), 'm3'=>$sumSlice($fcM, $lastIdx), 'euro'=>$sumSlice($fcE, $lastIdx)],
        ];

        $cum = function(array $arr) {
            $out=[]; $acc=0.0; foreach ($arr as $v){ $acc += (float)$v; $out[] = $acc; } return $out;
        };
        $salesCumM = $cum($salesM); $budCumM = $cum($budMAdj); $fcCumM = $cum($fcM);

        $pct = function(float $a, float $b): float { return ($b > 0) ? ($a/$b*100.0) : 0.0; };
        $trend = function(array $num, array $den, int $toIdx) use ($pct) {
            $out=[]; for ($i=0; $i<=max(0,$toIdx); $i++) $out[] = $pct($num[$i]??0, $den[$i]??0); return $out;
        };

        $attVsBudPct = $pct($ytdRaw['sales']['m3'], $ytdRaw['budgets']['m3']);
        $attVsFcPct  = $pct($ytdRaw['sales']['m3'], $ytdRaw['forecast']['m3']);
        $attVsBudTrend = $trend($salesCumM, $budCumM, $lastIdx);
        $attVsFcTrend  = $trend($salesCumM, $fcCumM, $lastIdx);

        // Future from next month to FY end
        $nextIdx = min(11, $lastIdx + 1);
        $futureIdxs = [];
        for ($k=$nextIdx; $k<12; $k++) $futureIdxs[] = $k;
        $pluckIdx = fn(array $a, array $idxs) => array_map(fn($i)=>$a[$i]??0.0, $idxs);
        $futureLabels = array_map(fn($i)=>$labels[$i], $futureIdxs);
        $sumArr = fn($arr)=>array_reduce($arr, fn($a,$b)=>$a+(float)$b, 0.0);

        $future = [
            'labels'   => $futureLabels,
            'budget'   => [
                'units' => $pluckIdx($budUAdj, $futureIdxs),
                'm3'    => $pluckIdx($budMAdj, $futureIdxs),
                'euro'  => $pluckIdx($budEAdj, $futureIdxs),
            ],
            'forecast' => [
                'units' => $pluckIdx($fcU, $futureIdxs),
                'm3'    => $pluckIdx($fcM, $futureIdxs),
                'euro'  => $pluckIdx($fcE, $futureIdxs),
            ],
        ];
        $future['totals'] = [
            'budget'   => ['units'=>$sumArr($future['budget']['units']),   'm3'=>$sumArr($future['budget']['m3']),   'euro'=>$sumArr($future['budget']['euro'])],
            'forecast' => ['units'=>$sumArr($future['forecast']['units']), 'm3'=>$sumArr($future['forecast']['m3']), 'euro'=>$sumArr($future['forecast']['euro'])],
        ];

        $fyStartDate = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-04-01', $fyStart))->setTime(0,0,0);
        $cutTo = $asOfDate;

        // Chances (safe try-catch)
        $chancesTotal = 0; $chancesByUser = [];
        try {
            $chQ = DB::table('chances')
                ->where('profit_center_code', $pc)
                ->whereBetween('created_at', [$fyStartDate->format('Y-m-d H:i:s'), $cutTo->format('Y-m-d H:i:s')]);

            $chancesTotal = (int)$chQ->count();

            $rows = $chQ->select('user_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('user_id')
                ->get();

            $userIds = $rows->pluck('user_id')->filter()->unique()->values()->all();
            $names = [];
            if (!empty($userIds)) {
                $urows = DB::table('users')->whereIn('id', $userIds)->get(['id','first_name','last_name','name','email']);
                foreach ($urows as $u) {
                    $fn = trim((string)($u->first_name ?? ''));
                    $ln = trim((string)($u->last_name ?? ''));
                    $label = trim($ln . ($ln && $fn ? ', ' : '') . $fn);
                    if ($label === '') $label = (string)($u->name ?? $u->email ?? ('User '.$u->id));
                    $names[(int)$u->id] = $label;
                }
            }

            foreach ($rows as $r) {
                $uid = (int)$r->user_id;
                $chancesByUser[] = ['user_id'=>$uid, 'user_name'=>$names[$uid] ?? ('User '.$uid), 'count'=>(int)$r->cnt];
            }
            usort($chancesByUser, fn($a,$b)=>$b['count']<=>$a['count']);
        } catch (\Throwable $e) {
            $chancesTotal = 0;
            $chancesByUser = [];
        }

        $extra = [
            'allocated' => ['units'=>$extraU, 'm3'=>$extraM, 'euro'=>$extraE],
            'remaining' => ['units'=>0.0, 'm3'=>0.0, 'euro'=>0.0],
        ];

        $fmt = fn($n) => number_format((float)$n, 0, ',', '.');
        $fmtArr = fn($arr) => array_map($fmt, $arr);

        return response()->json([
            'profit_center_code' => $pc,
            'fy_start' => $fyStart,
            'fy_label' => $fyLabel, // German
            'months'   => $labels,
            'last_complete_index' => $lastIdx,
            'as_of' => $asOfDate->format('Y-m'),
            'ytd' => [
                'sales'    => ['units'=>$fmt($ytdRaw['sales']['units']), 'm3'=>$fmt($ytdRaw['sales']['m3']), 'euro'=>$fmt($ytdRaw['sales']['euro'])],
                'budgets'  => ['units'=>$fmt($ytdRaw['budgets']['units']), 'm3'=>$fmt($ytdRaw['budgets']['m3']), 'euro'=>$fmt($ytdRaw['budgets']['euro'])],
                'forecast' => ['units'=>$fmt($ytdRaw['forecast']['units']),'m3'=>$fmt($ytdRaw['forecast']['m3']),'euro'=>$fmt($ytdRaw['forecast']['euro'])],
                'attainment' => [
                    'vs_budget'   => ['pct'=>round($pct($ytdRaw['sales']['m3'], $ytdRaw['budgets']['m3']), 1), 'trend'=>array_map(fn($v)=>round($v,1), $trend($salesCumM, $budCumM, $lastIdx))],
                    'vs_forecast' => ['pct'=>round($pct($ytdRaw['sales']['m3'], $ytdRaw['forecast']['m3']), 1),  'trend'=>array_map(fn($v)=>round($v,1), $trend($salesCumM, $fcCumM,  $lastIdx))],
                ],
            ],
            'future_6m' => [
                'labels' => $future['labels'],
                'budget' => [
                    'units' => $fmtArr($future['budget']['units']),
                    'm3'    => $fmtArr($future['budget']['m3']),
                    'euro'  => $fmtArr($future['budget']['euro']),
                ],
                'forecast' => [
                    'units' => $fmtArr($future['forecast']['units']),
                    'm3'    => $fmtArr($future['forecast']['m3']),
                    'euro'  => $fmtArr($future['forecast']['euro']),
                ],
                'totals' => [
                    'budget' => ['units'=>$fmt($future['totals']['budget']['units']), 'm3'=>$fmt($future['totals']['budget']['m3']), 'euro'=>$fmt($future['totals']['budget']['euro'])],
                    'forecast' => ['units'=>$fmt($future['totals']['forecast']['units']), 'm3'=>$fmt($future['totals']['forecast']['m3']), 'euro'=>$fmt($future['totals']['forecast']['euro'])],
                ],
            ],
            'chances' => [
                'total'   => $chancesTotal,
                'by_user' => $chancesByUser,
            ],
            'extra_quota' => [
                'allocated' => ['units'=>$fmt($extra['allocated']['units']), 'm3'=>$fmt($extra['allocated']['m3']), 'euro'=>$fmt($extra['allocated']['euro'])],
                'remaining' => ['units'=>$fmt($extra['remaining']['units']), 'm3'=>$fmt($extra['remaining']['m3']), 'euro'=>$fmt($extra['remaining']['euro'])],
            ],
            'raw' => [
                'monthly' => [
                    'sales'    => ['units'=>$salesU, 'm3'=>$salesM, 'euro'=>$salesE],
                    'budgets'  => ['units'=>$budUAdj, 'm3'=>$budMAdj, 'euro'=>$budEAdj],
                    'forecast' => ['units'=>$fcU, 'm3'=>$fcM, 'euro'=>$fcE],
                ],
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