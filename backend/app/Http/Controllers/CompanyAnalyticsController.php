<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyAnalyticsController extends Controller
{
public function tree(Request $request)
{
    $nodeId = $request->input('node_id', 'root');

    if ($nodeId === 'root') {
        return response()->json([[
            'id' => 'company_main',
            'label' => config('app.company_name', env('COMPANY_NAME', 'Steinbacher')),
            'has_children' => true,
            'type' => 'company',
            // ⬇️ cargamos TODO el árbol
            'children' => $this->treeTeams(),
        ]]);
    }

    // Como ya viene todo pre-cargado, las expansiones no necesitan pedir más
    return response()->json([]);
}

private function treeTeams(): array
{
    $teams = \Illuminate\Support\Facades\DB::table('teams')
        ->select('id','name','manager_user_id')
        ->orderBy('name')
        ->get();

    if ($teams->isEmpty()) return [];

    $teamIds = $teams->pluck('id')->all();

    // Users declarados en team_members
    $teamMembers = \Illuminate\Support\Facades\DB::table('team_members')
        ->whereIn('team_members.team_id', $teamIds)
        ->select('team_members.team_id','team_members.user_id','team_members.role')
        ->get();

    // Assignments (para PCs y Clientes, y también usuarios activos por team)
    $as = \Illuminate\Support\Facades\DB::table('assignments')
        ->whereIn('assignments.team_id', $teamIds)
        ->leftJoin('client_profit_centers','client_profit_centers.id','=','assignments.client_profit_center_id')
        ->leftJoin('profit_centers','profit_centers.profit_center_code','=','client_profit_centers.profit_center_code')
        ->leftJoin('clients','clients.client_group_number','=','client_profit_centers.client_group_number')
        ->select(
            'assignments.team_id',
            'assignments.user_id',
            'client_profit_centers.profit_center_code',
            'profit_centers.profit_center_name',
            'clients.client_group_number',
            'clients.client_name'
        )
        ->get();

    // Conjunto de user_ids por team (manager + members + assignments)
    $userIdsAll = [];
    $usersByTeam = [];
    foreach ($teams as $t) {
        if ($t->manager_user_id) {
            $usersByTeam[$t->id][(int)$t->manager_user_id] = 'manager';
            $userIdsAll[] = (int)$t->manager_user_id;
        }
    }
    foreach ($teamMembers as $m) {
        $role = strtolower((string)$m->role) === 'manager' ? 'manager' : 'seller';
        $usersByTeam[$m->team_id][(int)$m->user_id] = $role;
        $userIdsAll[] = (int)$m->user_id;
    }
    foreach ($as as $r) {
        if ($r->user_id) {
            $usersByTeam[$r->team_id][(int)$r->user_id] = $usersByTeam[$r->team_id][(int)$r->user_id] ?? 'seller';
            $userIdsAll[] = (int)$r->user_id;
        }
    }
    $userIdsAll = array_values(array_unique($userIdsAll));

    // Nombres de usuarios "Apellido, Nombre" (fallback a name o "User {id}")
    $display = $this->fetchUsersDisplay($userIdsAll);

    // Mapa team->user->pc->clients
    $map = []; // [team][user][pc] => ['pc_name'=>..., 'clients'=>[]]
    foreach ($as as $r) {
        if (!$r->user_id) continue;
        $tid = (int)$r->team_id;
        $uid = (int)$r->user_id;
        $pc  = (string)$r->profit_center_code;
        $pcn = (string)($r->profit_center_name ?? $pc);
        $cgn = $r->client_group_number ? (string)$r->client_group_number : null;
        $cnm = $r->client_name ? (string)$r->client_name : null;

        $map[$tid]           = $map[$tid]           ?? [];
        $map[$tid][$uid]     = $map[$tid][$uid]     ?? [];
        $map[$tid][$uid][$pc]= $map[$tid][$uid][$pc]?? ['pc_name'=>$pcn,'clients'=>[],'_seen'=>[]];

        if ($cgn && !isset($map[$tid][$uid][$pc]['_seen'][$cgn])) {
            $map[$tid][$uid][$pc]['clients'][] = ['client_group_number'=>$cgn, 'client_name'=>$cnm ?? $cgn];
            $map[$tid][$uid][$pc]['_seen'][$cgn] = true;
        }
    }
    // ordenar clientes y limpiar flags
    foreach ($map as $tid => &$users) {
        foreach ($users as $uid => &$pcs) {
            foreach ($pcs as $pc => &$info) {
                unset($info['_seen']);
                usort($info['clients'], fn($a,$b)=>strcmp($a['client_name'],$b['client_name']));
            }
            ksort($pcs);
        }
    }
    unset($users,$pcs,$info);

    // Construcción de nodos
    $out = [];
    foreach ($teams as $t) {
        $teamNode = [
            'id' => 'team_' . $t->id,
            'label' => $t->name,
            'has_children' => true,
            'type' => 'team',
            'children' => [],
        ];

        $uids = array_keys($usersByTeam[$t->id] ?? []);
        sort($uids);

        foreach ($uids as $uid) {
            $isMgr = ($usersByTeam[$t->id][$uid] ?? 'seller') === 'manager';
            $labelUser = $display[$uid] ?? ('User ' . $uid);
            $userNode = [
                'id' => 'user_' . $uid . '_t' . $t->id,
                'label' => $labelUser . ($isMgr ? ' (Manager)' : ''),
                'has_children' => true,
                'type' => 'user',
                'children' => [],
            ];

            // PCs del usuario
            if (isset($map[$t->id][$uid])) {
                foreach ($map[$t->id][$uid] as $pcCode => $info) {
                    $pcNode = [
                        'id' => 'pc_' . $pcCode . '_u' . $uid . '_t' . $t->id,
                        'label' => $info['pc_name'], // solo nombre
                        'has_children' => true,
                        'type' => 'pc',
                        'children' => [],
                    ];
                    // Clientes del PC
                    foreach ($info['clients'] as $cl) {
                        $pcNode['children'][] = [
                            'id' => 'client_' . $cl['client_group_number'] . '_pc' . $pcCode . '_u' . $uid . '_t' . $t->id,
                            'label' => $cl['client_name'], // solo nombre
                            'has_children' => false,
                            'type' => 'client',
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

private function fetchUsersDisplay(array $ids): array
{
    // normalizar IDs
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (empty($ids)) return [];

    // Trae TODO de users (sin asumir nombres de columnas)
    $rows = DB::table('users')
        ->whereIn('users.id', $ids)
        ->get();

    $map = [];
    foreach ($rows as $u) {
        $id  = (int)($u->id ?? 0);
        $arr = (array)$u;

        // Helpers para tomar el primer campo no vacío
        $pick = function(array $keys) use ($arr): string {
            foreach ($keys as $k) {
                if (array_key_exists($k, $arr)) {
                    $v = trim((string)$arr[$k]);
                    if ($v !== '') return $v;
                }
            }
            return '';
        };

        // Intento 1: Apellido + Nombre
        $last  = $pick(['last_name','apellido','apellidos','surname','family_name','LastName','lastName']);
        $first = $pick(['first_name','nombre','given_name','FirstName','firstName']);

        if ($last !== '' || $first !== '') {
            $label = trim($last . ($last !== '' && $first !== '' ? ', ' : '') . $first, ' ,');
        } else {
            // Intento 2: full/display/name, username, email
            $label = $pick(['full_name','display_name','name','username','email']);
            if ($label === '') $label = 'User ' . $id;
        }

        $map[$id] = $label;
    }

    // Completar faltantes por si algún id no vino
    foreach ($ids as $id) {
        if (!isset($map[$id])) $map[$id] = 'User ' . $id;
    }

    return $map;
}

    public function totals(Request $request)
{
    $nodeId = (string)$request->input('node_id', '');
    if ($nodeId === '') {
        return response()->json(['error' => 'node_id is required'], 422);
    }

    $ctx = $this->parseNodeId($nodeId);

    // ---- Período opcional
    $fy   = $request->integer('fiscal_year');   // ej 2025
    $from = $request->input('from');            // YYYY-MM
    $to   = $request->input('to');              // YYYY-MM

    // ---- 1) Subconjunto de CPCs por contexto (evita duplicados por múltiples assignments)
    $cpcs = \Illuminate\Support\Facades\DB::table('assignments')
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

    // Helper de período
    $applyPeriod = function($q, $tbl) use ($fy, $from, $to) {
        if ($fy) $q->where($tbl . '.fiscal_year', $fy);
        if ($from && $to && preg_match('/^\d{4}-\d{2}$/',$from) && preg_match('/^\d{4}-\d{2}$/',$to)) {
            [$yf,$mf] = array_map('intval', explode('-', $from));
            [$yt,$mt] = array_map('intval', explode('-', $to));
            $q->whereRaw('(' . $tbl . '.fiscal_year*100 + ' . $tbl . '.month) between ? and ?', [$yf*100+$mf, $yt*100+$mt]);
        }
    };

    // ---- 2) SALES (unidades -> m3 -> €)
    $sales = \Illuminate\Support\Facades\DB::table('sales')
        ->joinSub($cpcs, 'cpcs', function($j){ $j->on('cpcs.cpc_id', '=', 'sales.client_profit_center_id'); })
        ->leftJoin('unit_conversions', 'unit_conversions.profit_center_code', '=', 'cpcs.profit_center_code');
    $applyPeriod($sales, 'sales');
    $salesRows = $sales->select(
            'cpcs.profit_center_code',
            \Illuminate\Support\Facades\DB::raw('SUM(sales.volume) as units'),
            \Illuminate\Support\Facades\DB::raw('SUM(sales.volume * COALESCE(unit_conversions.factor_to_m3,1)) as m3'),
            \Illuminate\Support\Facades\DB::raw('SUM(sales.volume * COALESCE(unit_conversions.factor_to_m3,1) * COALESCE(unit_conversions.factor_to_euro,0)) as euro')
        )
        ->groupBy('cpcs.profit_center_code')
        ->get();

    // ---- 3) BUDGETS (unidades -> m3 -> €)
    $budgets = \Illuminate\Support\Facades\DB::table('budgets')
        ->joinSub($cpcs, 'cpcs', function($j){ $j->on('cpcs.cpc_id', '=', 'budgets.client_profit_center_id'); })
        ->leftJoin('unit_conversions', 'unit_conversions.profit_center_code', '=', 'cpcs.profit_center_code');
    $applyPeriod($budgets, 'budgets');
    $budgetRows = $budgets->select(
            'cpcs.profit_center_code',
            \Illuminate\Support\Facades\DB::raw('SUM(budgets.volume) as units'),
            \Illuminate\Support\Facades\DB::raw('SUM(budgets.volume * COALESCE(unit_conversions.factor_to_m3,1)) as m3'),
            \Illuminate\Support\Facades\DB::raw('SUM(budgets.volume * COALESCE(unit_conversions.factor_to_m3,1) * COALESCE(unit_conversions.factor_to_euro,0)) as euro')
        )
        ->groupBy('cpcs.profit_center_code')
        ->get();

    // ---- 4) FORECASTS (última versión)  — si el contexto es de usuario/pc/cliente, usamos solo ese user_id
    $latestScopeHasUser = in_array($ctx['type'], ['user','pc','client']) && !empty($ctx['user_id']);

    $fcBase = \Illuminate\Support\Facades\DB::table('forecasts')
        ->when($latestScopeHasUser, function($q) use ($ctx) {
            $q->where('forecasts.user_id', $ctx['user_id']);
        })
        ->select(
            'forecasts.client_profit_center_id',
            'forecasts.fiscal_year',
            'forecasts.month',
            $latestScopeHasUser ? 'forecasts.user_id' : \Illuminate\Support\Facades\DB::raw('NULL as user_id'),
            \Illuminate\Support\Facades\DB::raw('MAX(forecasts.version) as max_version')
        )
        ->groupBy('forecasts.client_profit_center_id', 'forecasts.fiscal_year', 'forecasts.month')
        ->when($latestScopeHasUser, function($q){
            $q->groupBy('forecasts.user_id');
        });

    $forecasts = \Illuminate\Support\Facades\DB::table('forecasts')
        ->joinSub($fcBase, 'lv', function($j) use ($latestScopeHasUser) {
            $j->on('forecasts.client_profit_center_id', '=', 'lv.client_profit_center_id')
              ->on('forecasts.fiscal_year', '=', 'lv.fiscal_year')
              ->on('forecasts.month', '=', 'lv.month')
              ->on('forecasts.version', '=', 'lv.max_version');
            if ($latestScopeHasUser) {
                $j->on('forecasts.user_id', '=', 'lv.user_id');
            }
        })
        ->joinSub($cpcs, 'cpcs', function($j){ $j->on('cpcs.cpc_id', '=', 'forecasts.client_profit_center_id'); })
        ->leftJoin('unit_conversions', 'unit_conversions.profit_center_code', '=', 'cpcs.profit_center_code');

    $applyPeriod($forecasts, 'forecasts');
    if ($latestScopeHasUser) {
        $forecasts->where('forecasts.user_id', $ctx['user_id']);
    }

    $forecastRows = $forecasts->select(
            'cpcs.profit_center_code',
            \Illuminate\Support\Facades\DB::raw('SUM(forecasts.volume) as units'),
            \Illuminate\Support\Facades\DB::raw('SUM(forecasts.volume * COALESCE(unit_conversions.factor_to_m3,1)) as m3'),
            \Illuminate\Support\Facades\DB::raw('SUM(forecasts.volume * COALESCE(unit_conversions.factor_to_m3,1) * COALESCE(unit_conversions.factor_to_euro,0)) as euro')
        )
        ->groupBy('cpcs.profit_center_code')
        ->get();

    // helper suma
    $sum = function($rows, $field){ return (float)collect($rows)->sum($field); };

    // En niveles por encima de PC mostramos m3 y €; en PC/cliente también devolvemos units (por si querés mostrarlas)
    $includeUnits = in_array($ctx['type'], ['pc','client']);

    $resp = [
        'level' => $ctx,
        'period' => ['fiscal_year' => $fy, 'from' => $from, 'to' => $to],
        'sales' => [
            'by_pc' => $salesRows,
            'total' => [
                'm3' => $sum($salesRows, 'm3'),
                'euro' => $sum($salesRows, 'euro'),
            ] + ($includeUnits ? ['units' => $sum($salesRows, 'units')] : []),
        ],
        'budgets' => [
            'by_pc' => $budgetRows,
            'total' => [
                'm3' => $sum($budgetRows, 'm3'),
                'euro' => $sum($budgetRows, 'euro'),
            ] + ($includeUnits ? ['units' => $sum($budgetRows, 'units')] : []),
        ],
        'forecasts' => [
            'by_pc' => $forecastRows,
            'total' => [
                'm3' => $sum($forecastRows, 'm3'),
                'euro' => $sum($forecastRows, 'euro'),
            ] + ($includeUnits ? ['units' => $sum($forecastRows, 'units')] : []),
        ],
    ];

    return response()->json($resp);
}

    private function parseNodeId(string $nodeId): array
    {
        if ($nodeId === 'company_main') return ['type' => 'company'];
        if (preg_match('/^team_(\d+)$/', $nodeId, $m)) return ['type' => 'team', 'team_id' => (int)$m[1]];
        if (preg_match('/^user_(\d+)_t(\d+)$/', $nodeId, $m)) return ['type' => 'user', 'user_id' => (int)$m[1], 'team_id' => (int)$m[2]];
        if (preg_match('/^pc_(.+)_u(\d+)_t(\d+)$/', $nodeId, $m)) return ['type' => 'pc', 'pc_code' => $m[1], 'user_id' => (int)$m[2], 'team_id' => (int)$m[3]];
        if (preg_match('/^client_(.+)_pc(.+)_u(\d+)_t(\d+)$/', $nodeId, $m)) {
            return [
                'type' => 'client',
                'client_group_number' => $m[1],
                'pc_code' => $m[2],
                'user_id' => (int)$m[3],
                'team_id' => (int)$m[4],
            ];
        }
        return ['type' => 'unknown'];
    }

    // Controller
    public function debugTeamUsers(Request $request)
    {
        $teamId = (int)$request->query('team_id');
        $team = DB::table('teams')->where('id', $teamId)->first(['id','manager_user_id']);

        $assignees = DB::table('assignments')
            ->where('assignments.team_id', $teamId)
            ->whereNotNull('assignments.user_id')
            ->distinct()
            ->pluck('assignments.user_id');

        $members = DB::table('team_members')
            ->where('team_members.team_id', $teamId)
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
}
