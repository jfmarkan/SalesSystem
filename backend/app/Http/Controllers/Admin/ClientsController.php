<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientsController extends Controller {
    public function summary()
    {
        $total   = DB::table('clients')->count();
        $active  = DB::table('clients')->whereNull('deleted_at')->count();
        $deleted = DB::table('clients')->whereNotNull('deleted_at')->count();

        $byClass = DB::table('clients')
            ->select('classification_id', DB::raw('COUNT(*) AS c'))
            ->groupBy('classification_id')
            ->get();

        $pcCounts = DB::table('client_profit_centers')
            ->select('client_group_number', DB::raw('COUNT(*) AS pcs'))
            ->groupBy('client_group_number')
            ->pluck('pcs', 'client_group_number');

        $recent = DB::table('clients')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return response()->json([
            'total' => $total,
            'active' => $active,
            'deleted' => $deleted,
            'by_classification' => $byClass,
            'pc_counts' => $pcCounts,
            'recent' => $recent,
        ]);
    }

    public function index() {
        $clients = DB::table('clients as c')
            ->leftJoin('classifications as cl','cl.id','=','c.classification_id')
            ->leftJoin('users as u', 'u.username', '=', 'c.group_responsible')
            ->leftJoin('user_details as ud', 'ud.user_id', '=', 'u.id')
            ->select(
                'c.client_group_number',
                'c.client_name',
                'c.classification_id',
                'cl.classification',
                'c.group_responsible',
                'u.first_name as responsible_name',
                'u.last_name as responsible_surname',)
            ->selectRaw("
                CASE
                    WHEN ud.profile_picture IS NOT NULL AND ud.profile_picture != ''
                    THEN CONCAT('" . config('app.url') . "/storage/', ud.profile_picture)
                    ELSE NULL
                END as responsible_avatar
            ")
            ->orderBy('c.client_name')
            ->get();

        $profitCenters = DB::table('client_profit_centers as cpc')
            ->join('profit_centers as p', 'p.profit_center_code', '=', 'cpc.profit_center_code')
            ->select(
                'cpc.client_group_number',
                'p.profit_center_code as code',
                'cpc.id'
            )
            ->get()
            ->groupBy('client_group_number');

        return response()->json($clients->map(function ($c) use ($profitCenters) {
            $c->profit_centers = $profitCenters[$c->client_group_number] ?? [];
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
