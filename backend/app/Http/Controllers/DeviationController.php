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

        $q = Deviation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('fiscal_year')
            ->orderByDesc('month');

        if ($type = $request->query('type')) {
            $q->where('deviation_type', $type);
        }

        // Traer solo los campos necesarios y renombrarlos si hace falta
        $deviations = $q->get()->map(function ($dev) {
            return [
                'id' => $dev->id,
                'type' => $dev->deviation_type ?? 'sales',
                'clientName' => $dev->client_name ?? $dev->client ?? $dev->kunde,
                'pcCode' => $dev->pc_code ?? $dev->code,
                'pcName' => $dev->pc_name ?? $dev->name,
                'year' => (int) ($dev->fiscal_year ?? $dev->year ?? 0),
                'month' => (int) ($dev->month ?? 0),
                'sales' => (float) ($dev->sales ?? 0),
                'budget' => (float) ($dev->budget ?? 0),
                'forecast' => (float) ($dev->forecast ?? 0),
                'deltaAbs' => (float) ($dev->delta_abs ?? 0),
                'deltaPct' => (float) ($dev->delta_pct ?? 0),
                'comment' => $dev->explanation ?? '',
                'justified' => (bool) ($dev->justified ?? ($dev->explanation ? true : false)),
                'months' => $dev->months ?? null,
                'salesSeries' => $dev->sales_series ?? null,
                'budgetSeries' => $dev->budget_series ?? null,
                'forecastSeries' => $dev->forecast_series ?? null,
            ];
        });

        return response()->json($deviations);
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
