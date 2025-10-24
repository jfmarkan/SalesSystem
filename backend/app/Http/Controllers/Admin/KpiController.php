<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class KpiController extends Controller
{
    public function index()
    {
        $usersTotal  = DB::table('users')->count();
        $usersOnline = DB::table('sessions')->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $clients     = DB::table('clients')->count();
        $pcs         = DB::table('profit_centers')->count();

        return response()->json([
            'users_total'  => $usersTotal,
            'users_online' => $usersOnline,
            'clients_total'=> $clients,
            'pcs_total'    => $pcs,
        ]);
    }

    private function onlineMap(): array
    {
        $threshold = now()->subMinutes(5)->timestamp;
        $rows = DB::table('sessions')
            ->select('user_id','last_activity')
            ->whereNotNull('user_id')
            ->where('last_activity','>=',$threshold)
            ->get();

        $map = [];
        foreach ($rows as $r) $map[$r->user_id] = $r->last_activity;
        return $map;
    }

    private function currentFy(): int
    {
        $now = now();
        return $now->month >= 4 ? $now->year : $now->year - 1;
    }
}
