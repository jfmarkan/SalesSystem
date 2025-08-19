<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Deviation;
use App\Models\Justification;
use App\Models\ActionPlan;
use App\Models\ActionItem;

class DeviationController extends Controller
{
    // GET /api/deviations
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $q = Deviation::with(['justification'])
            ->where('user_id', $user->id)
            ->orderByDesc('fiscal_year')
            ->orderByDesc('month');

        if ($type = $request->query('type')) {
            $q->where('deviation_type', strtoupper($type));
        }

        $deviations = $q->get()->map(function (Deviation $dev) {
            $budget = (float)($dev->budget ?? 0);
            $sales  = (float)($dev->sales ?? 0);
            $fcst   = (float)($dev->forecast ?? 0);

            $ref = strtoupper($dev->deviation_type) === 'FORECAST' ? $fcst : $sales;
            $deltaAbs = $dev->delta_abs ?? ($budget !== 0 ? ($ref - $budget) : null);
            $deltaPct = $dev->delta_pct ?? ($budget > 0 ? (($ref - $budget) / $budget) * 100.0 : null);

            return [
                'id' => $dev->id,
                'type' => strtolower($dev->deviation_type ?? 'SALES'),
                'clientName' => $dev->client_name ?: null,
                'pcCode' => $dev->profit_center_code,
                'pcName' => $dev->pc_name,
                'year' => (int)$dev->fiscal_year,
                'month' => (int)$dev->month,
                'sales' => (float)$sales,
                'budget' => (float)$budget,
                'forecast' => (float)$fcst,
                'deltaAbs' => is_null($deltaAbs) ? 0.0 : (float)$deltaAbs,
                'deltaPct' => is_null($deltaPct) ? 0.0 : (float)$deltaPct,
                'comment' => $dev->justification->comment ?? '',
                'justified' => (bool)$dev->justified,
                'months' => $dev->months,
                'salesSeries' => $dev->sales_series,
                'budgetSeries' => $dev->budget_series,
                'forecastSeries' => $dev->forecast_series,
            ];
        });

        return response()->json($deviations);
    }

    // POST /api/deviations/run
    public function runForMe(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        Artisan::call('deviations:detect', ['--user_id' => $user->id]);
        return response()->json(['message' => 'Deviation detection executed for current user.']);
    }

    // PUT /api/deviations/{id}/justify
    public function justify(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $dev = Deviation::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        $v = Validator::make($request->all(), [
            'comment' => ['nullable', 'string'],
            'explanation' => ['nullable', 'string'], // backwards-compat
            'plan' => ['nullable', 'string'],
            'actions' => ['nullable', 'array'],
            'actions.*.title' => ['required_with:actions', 'string', 'max:255'],
            'actions.*.desc'  => ['nullable', 'string'],
            'actions.*.due'   => ['nullable', 'date_format:Y-m-d'],
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Invalid payload', 'errors' => $v->errors()], 422);
        }

        $comment  = $request->input('comment', $request->input('explanation'));
        $planText = $request->input('plan');
        $actions  = $request->input('actions', []);

        // 1) Upsert justification (1:1). SALES: only comment; FORECAST: comment + textual plan.
        $just = Justification::firstOrNew(['deviation_id' => $dev->id]);
        $just->user_id = $user->id;
        $just->type = strtoupper($dev->deviation_type) === 'FORECAST' ? 'FORECAST' : 'SALES';
        $just->comment = $comment;
        $just->plan = $just->type === 'FORECAST' ? $planText : null;
        $just->save();

        // 2) FORECAST → upsert ActionPlan + replace ActionItems
        if (strtoupper($dev->deviation_type) === 'FORECAST' && ($planText || !empty($actions))) {
            $plan = ActionPlan::firstOrNew(['deviation_id' => $dev->id]);
            $plan->user_id = $user->id;
            $plan->objective = $planText;
            $plan->save();

            DB::transaction(function () use ($plan, $actions) {
                ActionItem::where('action_plan_id', $plan->id)->delete();

                foreach ($actions as $a) {
                    $title = trim($a['title'] ?? '');
                    if ($title === '') continue;

                    ActionItem::create([
                        'action_plan_id' => $plan->id,
                        'title' => $title,
                        'description' => $a['desc'] ?? $a['description'] ?? null,
                        'due_date' => $a['due'] ?? null,
                    ]);
                }
            });
        }

        // 3) Mark as justified
        $dev->justified = true;
        $dev->save();

        return response()->json(['message' => 'Deviation updated']);
    }
}