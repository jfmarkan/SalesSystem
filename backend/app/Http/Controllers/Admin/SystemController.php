<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemFlags;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller {
    public function flagsGet(SystemFlags $flags) { return response()->json($flags->get()); }
    public function flagsPut(Request $request, SystemFlags $flags) {
        $data = $request->validate(['maintenance'=>'boolean','budget_period_active'=>'boolean']);
        return response()->json($flags->set($data));
    }
    public function logsIndex() {
        $rows = DB::table('activity_log')->orderByDesc('id')->limit(500)->get();
        return response()->json($rows);
    }
    public function logsStore(Request $request) {
        $data = $request->validate(['level'=>'required|in:INFO,WARN,ERROR','message'=>'required|string','context'=>'array']);
        DB::table('activity_log')->insert([
            'log_name'=>'admin','description'=>$data['message'],'event'=>$data['level'],
            'properties'=>json_encode($data['context']??[]),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return response()->json(['ok'=>true], 201);
    }
}
