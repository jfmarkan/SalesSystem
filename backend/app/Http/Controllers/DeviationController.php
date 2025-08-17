<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deviation;

class DeviationController extends Controller
{
    // GET /api/deviations
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message'=>'Unauthenticated'], 401);

        $q = Deviation::query()->where('user_id', $user->id)
            ->orderByDesc('fiscal_year')->orderByDesc('month');

        if ($type = $request->query('type')) {
            $q->where('deviation_type', $type);
        }

        return response()->json($q->get());
    }

    // POST /api/deviations/run
    public function runForMe(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message'=>'Unauthenticated'], 401);

        \Artisan::call('deviations:detect', ['--user_id' => $user->id]);
        return response()->json(['message' => 'Deviation detection executed for current user.']);
    }

    // PUT /api/deviations/{id}/justify
    public function justify(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message'=>'Unauthenticated'], 401);

        $dev = Deviation::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $dev->update([
            'explanation' => $request->input('explanation', $dev->explanation),
        ]);

        return response()->json(['message' => 'Deviation updated']);
    }
}
