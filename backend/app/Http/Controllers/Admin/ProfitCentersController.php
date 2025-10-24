<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitCentersController extends Controller {
    public function summary()
    {
        $total    = DB::table('profit_centers')->count();
        $active   = DB::table('profit_centers')->whereNull('deleted_at')->count();
        $archived = DB::table('profit_centers')->whereNotNull('deleted_at')->count();

        $clientCounts = DB::table('client_profit_centers')
            ->select('profit_center_code', DB::raw('COUNT(*) AS clients'))
            ->groupBy('profit_center_code')
            ->pluck('clients', 'profit_center_code');

        $seasonalityCoverage = DB::table('seasonalities')
            ->select('profit_center_code')
            ->distinct()
            ->count();

        $conversionCoverage = DB::table('unit_conversions')
            ->select('profit_center_code')
            ->distinct()
            ->count();

        $recent = DB::table('profit_centers')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return response()->json([
            'total' => $total,
            'active' => $active,
            'archived' => $archived,
            'clients_per_pc' => $clientCounts,
            'seasonality_covered' => $seasonalityCoverage,
            'conversion_covered' => $conversionCoverage,
            'recent' => $recent,
        ]);
    }
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
