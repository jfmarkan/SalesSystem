<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetsController extends Controller {
    public function casesIndex(Request $request) {
        $fy = (int)($request->query('fiscal_year') ?? date('Y')+1);
        return response()->json(DB::table('budget_cases')->where('fiscal_year',$fy)->get());
    }
    public function casesUpsert(Request $request) {
        $data = $request->validate([
            'client_profit_center_id'=>'required|exists:client_profit_centers,id',
            'fiscal_year'=>'required|integer',
            'best_case'=>'numeric',
            'worst_case'=>'numeric',
        ]);
        $row = DB::table('budget_cases')->where([
            'client_profit_center_id'=>$data['client_profit_center_id'],
            'fiscal_year'=>$data['fiscal_year'],
        ])->first();
        if ($row) {
            DB::table('budget_cases')->where('id',$row->id)->update([
                'best_case'=>$data['best_case'] ?? 0,'worst_case'=>$data['worst_case'] ?? 0,'updated_at'=>now()
            ]);
            $out = DB::table('budget_cases')->find($row->id);
        } else {
            $id = DB::table('budget_cases')->insertGetId([
                'client_profit_center_id'=>$data['client_profit_center_id'],'fiscal_year'=>$data['fiscal_year'],
                'best_case'=>$data['best_case'] ?? 0,'worst_case'=>$data['worst_case'] ?? 0,'created_at'=>now(),'updated_at'=>now()
            ]);
            $out = DB::table('budget_cases')->find($id);
        }
        return response()->json($out);
    }
    public function createNextYear(Request $request) {
        $fy = (int)($request->input('fiscal_year') ?? date('Y')+1);
        $cpcs = DB::table('client_profit_centers')->pluck('id');
        $exists = DB::table('budgets')->where('fiscal_year',$fy)->pluck('id','client_profit_center_id');
        $months = [4,5,6,7,8,9,10,11,12,1,2,3];
        $toInsert = [];
        foreach ($cpcs as $cpcId) {
            if (!isset($exists[$cpcId])) {
                foreach ($months as $m) {
                    $toInsert[] = [
                        'client_profit_center_id'=>$cpcId, 'fiscal_year'=>$fy, 'month'=>$m,
                        'volume'=>0, 'created_at'=>now(), 'updated_at'=>now()
                    ];
                }
            }
        }
        if ($toInsert) DB::table('budgets')->insert($toInsert);
        return response()->json(['ok'=>true,'fiscal_year'=>$fy,'created'=>count($toInsert)]);
    }
    public function clientsWithBestWorst(Request $request) {
        $fy = (int)($request->query('fiscal_year') ?? date('Y'));
        $rows = DB::table('clients as c')
            ->leftJoin('client_profit_centers as cpc','cpc.client_group_number','=','c.client_group_number')
            ->leftJoin('budget_cases as bc', function($j) use ($fy){ $j->on('bc.client_profit_center_id','=','cpc.id')->where('bc.fiscal_year','=',$fy); })
            ->select('c.client_group_number','c.client_name',
                DB::raw('SUM(CASE WHEN bc.best_case IS NOT NULL AND bc.best_case <> 0 THEN 1 ELSE 0 END) as best_cnt'),
                DB::raw('SUM(CASE WHEN bc.worst_case IS NOT NULL AND bc.worst_case <> 0 THEN 1 ELSE 0 END) as worst_cnt'))
            ->groupBy('c.client_group_number','c.client_name')->orderBy('c.client_name')->get();
        return response()->json($rows);
    }
}
