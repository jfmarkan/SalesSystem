<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class SessionAdminController extends Controller
{
    public function online()
    {
        $threshold = now()->subMinutes(5)->timestamp;
        $rows = DB::table('sessions')
            ->select('user_id','last_activity')
            ->whereNotNull('user_id')
            ->where('last_activity','>=',$threshold)
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->user_id] = $r->last_activity;
        }
        return response()->json($map);
    }

    public function destroyByUser($id)
    {
        DB::table('sessions')->where('user_id', $id)->delete();
        return response()->json(['ok' => true]);
    }
}
