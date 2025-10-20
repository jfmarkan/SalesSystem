<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitCentersController extends Controller {
    public function index() {
        return response()->json(DB::table('profit_centers')->orderBy('profit_center_code')->get());
    }
    public function link(Request $request) {
        $data = $request->validate([
            'client_group_number'=>'required|integer|exists:clients,client_group_number',
            'profit_center_code'=>'required|integer|exists:profit_centers,profit_center_code',
        ]);
        $exists = DB::table('client_profit_centers')->where($data)->exists();
        if (!$exists) DB::table('client_profit_centers')->insert($data + ['created_at'=>now(),'updated_at'=>now()]);
        $rel = DB::table('client_profit_centers')->where($data)->first();
        return response()->json($rel, 201);
    }
    public function unlink(int $relationId) {
        DB::table('client_profit_centers')->where('id',$relationId)->update(['deleted_at'=>now()]);
        return response()->json(['ok'=>true]);
    }
}
