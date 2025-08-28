<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyAnalyticsController extends Controller
{
    public function tree(Request $request)
    {
        $companyLabel = config('app.company_name', env('COMPANY_NAME', 'Steinbacher Dämmstoffe'));

        $teams = DB::table('teams')
            ->select('id', 'name', 'manager_user_id')
            ->orderBy('name')->get();

        if ($teams->isEmpty()) {
            return response()->json([[
                'key' => 'company_main',
                'label' => $companyLabel,
                'data' => ['type' => 'company'],
                'children' => [],
            ]]);
        }

        $teamIds = $teams->pluck('id')->all();

        $members = DB::table('team_members as tm')
            ->join('users as u', 'u.id', '=', 'tm.user_id')
            ->whereIn('tm.team_id', $teamIds)
            ->select('tm.team_id', 'tm.user_id', 'tm.role', 'u.name')
            ->get();

        $as = DB::table('assignments as a')
            ->whereIn('a.team_id', $teamIds)
            ->join('client_profit_centers as cpc', 'cpc.id', '=', 'a.client_profit_center_id')
            ->join('clients as c', 'c.client_group_number', '=', 'cpc.client_group_number')
            ->leftJoin('profit_centers as pc', 'pc.profit_center_code', '=', 'cpc.profit_center_code')
            ->select(
                'a.team_id',
                'a.user_id',
                'cpc.profit_center_code',
                DB::raw('COALESCE(pc.profit_center_name, cpc.profit_center_code) as profit_center_name'),
                'c.client_group_number',
                'c.client_name'
            )->get();

        $userIds = [];
        foreach ($teams as $t) { if ($t->manager_user_id) $userIds[] = (int)$t->manager_user_id; }
        foreach ($members as $m) $userIds[] = (int)$m->user_id;
        foreach ($as as $r) $userIds[] = (int)$r->user_id;
        $userIds = array_values(array_unique($userIds));

        $userNames = $userIds
            ? DB::table('users')->whereIn('id', $userIds)->pluck('name','id')->map(fn($v)=>(string)$v)->toArray()
            : [];

        $membersByTeam = [];
        foreach ($members as $m) {
            $role = strtolower((string)$m->role) === 'manager' ? 'manager' : 'seller';
            $membersByTeam[$m->team_id][$m->user_id] = ['role'=>$role,'name'=>$m->name];
        }

        $treeMap = [];
        foreach ($as as $r) {
            $tid = (int)$r->team_id; $uid = (int)$r->user_id;
            $pc  = (string)$r->profit_center_code; $pcn = (string)$r->profit_center_name;
            $cgn = (string)$r->client_group_number; $cnm = (string)$r->client_name;

            $treeMap[$tid]        ??= [];
            $treeMap[$tid][$uid]  ??= [];
            $treeMap[$tid][$uid][$pc] ??= ['pc_name'=>$pcn,'clients'=>[],'_seen'=>[]];

            if (!isset($treeMap[$tid][$uid][$pc]['_seen'][$cgn])) {
                $treeMap[$tid][$uid][$pc]['clients'][] = ['client_group_number'=>$cgn, 'client_name'=>$cnm];
                $treeMap[$tid][$uid][$pc]['_seen'][$cgn] = true;
            }
        }
        foreach ($treeMap as $tid => &$users) {
            foreach ($users as $uid => &$pcs) {
                foreach ($pcs as $pc => &$info) {
                    unset($info['_seen']);
                    usort($info['clients'], fn($a,$b)=>strcmp($a['client_name'],$b['client_name']));
                }
                ksort($pcs);
            }
        }
        unset($users,$pcs,$info);

        $companyNode = [
            'key' => 'company_main',
            'label' => $companyLabel,
            'data' => ['type'=>'company'],
            'children' => [],
        ];

        foreach ($teams as $t) {
            $teamNode = [
                'key' => "team_{$t->id}",
                'label' => $t->name,
                'data' => ['type'=>'team'],
                'children' => [],
            ];

            $userSet = [];
            if ($t->manager_user_id) $userSet[(int)$t->manager_user_id] = true;
            if (isset($membersByTeam[$t->id])) foreach ($membersByTeam[$t->id] as $uid => $_) $userSet[(int)$uid] = true;
            if (isset($treeMap[$t->id])) foreach ($treeMap[$t->id] as $uid => $_) $userSet[(int)$uid] = true;

            $userIdsTeam = array_keys($userSet);
            usort($userIdsTeam, fn($a,$b)=>strcmp($userNames[$a]??"User $a", $userNames[$b]??"User $b"));

            foreach ($userIdsTeam as $uid) {
                $name = $userNames[$uid] ?? "User $uid";
                $isMgr = ($t->manager_user_id && (int)$t->manager_user_id === (int)$uid)
                      || (($membersByTeam[$t->id][$uid]['role'] ?? '') === 'manager');

                $userNode = [
                    'key' => "user_{$uid}_t{$t->id}",
                    'label' => $name . ($isMgr ? ' (Manager)' : ''),
                    'data' => ['type'=>'user','role'=>$isMgr?'manager':'seller'],
                    'children' => [],
                ];

                if (isset($treeMap[$t->id][$uid])) {
                    foreach ($treeMap[$t->id][$uid] as $pcCode => $info) {
                        $pcNode = [
                            'key' => "pc_{$pcCode}_u{$uid}_t{$t->id}",
                            'label' => "{$info['pc_name']} ({$pcCode})",
                            'data' => ['type'=>'pc'],
                            'children' => [],
                        ];
                        foreach ($info['clients'] as $cl) {
                            $pcNode['children'][] = [
                                'key' => "client_{$cl['client_group_number']}_pc{$pcCode}_u{$uid}_t{$t->id}",
                                'label' => $cl['client_name'] . " [{$cl['client_group_number']}]",
                                'data' => ['type'=>'client'],
                                'leaf' => true,
                            ];
                        }
                        $userNode['children'][] = $pcNode;
                    }
                }

                $teamNode['children'][] = $userNode;
            }

            $companyNode['children'][] = $teamNode;
        }

        return response()->json([$companyNode]);
    }

    public function totals(Request $request)
    {
        $nodeId = (string)$request->input('node_id', '');
        if (!$nodeId) return response()->json(['error'=>'node_id is required'], 422);

        $p = $this->parseNodeId($nodeId);

        $q = DB::table('assignments as a')
            ->join('client_profit_centers as cpc', 'cpc.id', '=', 'a.client_profit_center_id')
            ->join('sales as s', 's.client_profit_center_id', '=', 'cpc.id')
            ->leftJoin('unit_conversions as uc', 'uc.profit_center_code', '=', 'cpc.profit_center_code');

        switch ($p['type']) {
            case 'company': break;
            case 'team':  $q->where('a.team_id', $p['team_id']); break;
            case 'user':  $q->where('a.user_id', $p['user_id']); if (!empty($p['team_id'])) $q->where('a.team_id',$p['team_id']); break;
            case 'pc':
                $q->where('cpc.profit_center_code', $p['pc_code']);
                if (!empty($p['user_id'])) $q->where('a.user_id', $p['user_id']);
                if (!empty($p['team_id'])) $q->where('a.team_id', $p['team_id']);
                break;
            case 'client':
                $q->join('clients as c', 'c.client_group_number', '=', 'cpc.client_group_number')
                  ->where('c.client_group_number', $p['client_group_number']);
                if (!empty($p['pc_code']))  $q->where('cpc.profit_center_code', $p['pc_code']);
                if (!empty($p['user_id']))  $q->where('a.user_id', $p['user_id']);
                if (!empty($p['team_id']))  $q->where('a.team_id', $p['team_id']);
                break;
            default: return response()->json(['error'=>'Invalid node type'], 400);
        }

        if ($fy = $request->integer('fiscal_year')) $q->where('s.fiscal_year', $fy);
        $from = $request->input('from'); $to = $request->input('to'); // YYYY-MM
        if ($from && $to && preg_match('/^\d{4}-\d{2}$/',$from) && preg_match('/^\d{4}-\d{2}$/',$to)) {
            [$yf,$mf] = array_map('intval', explode('-', $from));
            [$yt,$mt] = array_map('intval', explode('-', $to));
            $q->whereRaw('(s.fiscal_year*100 + s.month) between ? and ?', [$yf*100+$mf, $yt*100+$mt]);
        }

        $rows = $q->select(
                'cpc.profit_center_code',
                DB::raw('SUM(s.volume * COALESCE(uc.factor_to_m3,1)) as total_m3'),
                DB::raw('SUM(s.volume * COALESCE(uc.factor_to_m3,1) * COALESCE(uc.factor_to_euro,0)) as total_euro')
            )
            ->groupBy('cpc.profit_center_code')
            ->get();

        return response()->json([
            'totals_by_pc' => $rows,
            'total_absolute' => [
                'm3'   => (float)$rows->sum('total_m3'),
                'euro' => (float)$rows->sum('total_euro'),
            ],
        ]);
    }

    private function parseNodeId(string $nodeId): array
    {
        if ($nodeId === 'company_main') return ['type'=>'company'];
        if (preg_match('/^team_(\d+)$/', $nodeId, $m)) return ['type'=>'team','team_id'=>(int)$m[1]];
        if (preg_match('/^user_(\d+)_t(\d+)$/', $nodeId, $m)) return ['type'=>'user','user_id'=>(int)$m[1],'team_id'=>(int)$m[2]];
        if (preg_match('/^pc_(.+)_u(\d+)_t(\d+)$/', $nodeId, $m)) return ['type'=>'pc','pc_code'=>$m[1],'user_id'=>(int)$m[2],'team_id'=>(int)$m[3]];
        if (preg_match('/^client_(.+)_pc(.+)_u(\d+)_t(\d+)$/', $nodeId, $m)) {
            return ['type'=>'client','client_group_number'=>$m[1],'pc_code'=>$m[2],'user_id'=>(int)$m[3],'team_id'=>(int)$m[4]];
        }
        return ['type'=>'unknown'];
    }
}