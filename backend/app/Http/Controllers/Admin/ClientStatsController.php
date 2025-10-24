<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ClientStatsController extends Controller
{
    public function pcClientCounts()
    {
        $rows = DB::table('client_profit_centers')
            ->select('profit_center_code', DB::raw('COUNT(*) as cnt'))
            ->groupBy('profit_center_code')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->profit_center_code] = (int)$r->cnt;
        }
        return response()->json($map);
    }
}
