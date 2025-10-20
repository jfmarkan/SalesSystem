<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientsController extends Controller {
    public function index() {
        $clients = DB::table('clients as c')
            ->leftJoin('classifications as cl','cl.id','=','c.classification_id')
            ->select('c.client_group_number','c.client_name','c.classification_id','cl.classification')
            ->orderBy('c.client_name')->get();

        $cpc = DB::table('client_profit_centers')->select('client_group_number', DB::raw('COUNT(*) cnt'))->groupBy('client_group_number')->pluck('cnt','client_group_number');
        $users = DB::table('assignments')
            ->join('client_profit_centers as cpc','cpc.id','=','assignments.client_profit_center_id')
            ->select('cpc.client_group_number as cgn', DB::raw('COUNT(DISTINCT assignments.user_id) as ucnt'))
            ->groupBy('cpc.client_group_number')->pluck('ucnt','cgn');

        return response()->json($clients->map(function($c) use($cpc,$users){
            $c->cpc_count = (int)($cpc[$c->client_group_number] ?? 0);
            $c->user_count = (int)($users[$c->client_group_number] ?? 0);
            return $c;
        }));
    }

    public function show(int $client_group_number) {
        $client = DB::table('clients')->where('client_group_number',$client_group_number)->first();
        abort_if(!$client, 404);
        $relations = DB::table('client_profit_centers as cpc')
            ->join('profit_centers as p','p.profit_center_code','=','cpc.profit_center_code')
            ->where('cpc.client_group_number',$client_group_number)
            ->select('cpc.id','cpc.profit_center_code','p.profit_center_name')
            ->get();
        return response()->json(['client'=>$client,'profit_centers'=>$relations]);
    }

    public function store(Request $request) {
        $data = $request->validate([
            'client_group_number'=>'required|integer|unique:clients,client_group_number',
            'client_name'=>'required|string',
            'classification_id'=>'required|exists:classifications,id',
        ]);
        DB::table('clients')->insert($data + ['created_at'=>now(),'updated_at'=>now()]);
        return response()->json($data, 201);
    }

    public function update(Request $request, int $client_group_number) {
        $data = $request->validate([
            'client_name'=>'sometimes|string',
            'classification_id'=>'sometimes|exists:classifications,id',
        ]);
        DB::table('clients')->where('client_group_number',$client_group_number)->update($data + ['updated_at'=>now()]);
        $client = DB::table('clients')->where('client_group_number',$client_group_number)->first();
        return response()->json($client);
    }

    public function destroy(int $client_group_number) {
        DB::table('clients')->where('client_group_number',$client_group_number)->update(['deleted_at'=>now()]);
        return response()->json(['ok'=>true]);
    }
}
