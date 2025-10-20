<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ToolsController extends Controller {
    public function rebuildSales(Request $request) {
        $data = $request->validate(['from_date'=>'required|date']);
        // Placeholder rápido: marca en activity_log
        DB::table('activity_log')->insert([
            'log_name'=>'admin','description'=>'Sales rebuild requested','event'=>'INFO',
            'properties'=>json_encode(['from'=>$data['from_date']]),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return response()->json(['queued'=>true]);
    }
}
