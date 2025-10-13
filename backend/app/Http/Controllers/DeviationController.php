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

        $q = Deviation::with(['profitCenter', 'justification', 'actionPlan.items'])
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

            $items = optional($dev->actionPlan)->items ?? collect();

            return [
                'id' => $dev->id,
                'type' => strtolower($dev->deviation_type ?? 'SALES'),
                'clientName' => $dev->client_name ?: null,
                'pcCode' => $dev->profit_center_code,
                'pcName' => $dev->pc_name?: optional($dev->profitCenter)->profit_center_name,
                'year' => (int)$dev->fiscal_year,
                'month' => (int)$dev->month,
                'sales' => (float)$sales,
                'budget' => (float)$budget,
                'forecast' => (float)$fcst,
                'deltaAbs' => is_null($deltaAbs) ? 0.0 : (float)$deltaAbs,
                'deltaPct' => is_null($deltaPct) ? 0.0 : (float)$deltaPct,
                'comment' => $dev->justification->comment ?? '',
                'plan' => $dev->justification->plan ?? null, // plan objective for closed
                'actions' => $items->map(function ($it) {
                    return [
                        'title' => $it->title,
                        'desc'  => $it->description,
                        'due'   => $it->due_date ? $it->due_date->format('Y-m-d') : null,
                    ];
                })->values(),
                'justified' => (bool)$dev->justified,
                'months' => $dev->months,
                'salesSeries' => $dev->sales_series,
                'budgetSeries' => $dev->budget_series,
                'forecastSeries' => $dev->forecast_series,
            ];
        });

        return response()->json($deviations);
    }

    public function byUserAndMonth(Request $request)
    {
        // Manager analizando a un vendedor:
        // Params requeridos: user_id, year, month
        $v = Validator::make($request->all(), [
            'user_id' => ['required','integer','min:1'],
            'year'    => ['required','integer','min:2000','max:2100'],
            'month'   => ['required','integer','min:1','max:12'],
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Invalid params', 'errors' => $v->errors()], 422);
        }

        $userId = (int)$request->input('user_id');
        $year   = (int)$request->input('year');
        $month  = (int)$request->input('month');

        // TODO: autorización real (ej: Gate::allows('manage-sales', $request->user()))
        // Por ahora asumimos que el manager puede consultar cualquier user_id.

        $q = Deviation::with(['profitCenter', 'justification', 'actionPlan.items'])
            ->where('user_id', $userId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderByDesc('created_at');

        $deviations = $q->get()->map(function (Deviation $dev) {
            $budget = (float)($dev->budget ?? 0);
            $sales  = (float)($dev->sales ?? 0);
            $fcst   = (float)($dev->forecast ?? 0);

            $typeUpper = strtoupper($dev->deviation_type ?? 'SALES');
            $typeLower = $typeUpper === 'FORECAST' ? 'forecast' : 'sales';

            $ref = $typeUpper === 'FORECAST' ? $fcst : $sales;
            $deltaAbs = $dev->delta_abs ?? ($budget !== 0 ? ($ref - $budget) : null);
            $deltaPct = $dev->delta_pct ?? ($budget > 0 ? (($ref - $budget) / $budget) * 100.0 : null);

            $items = optional($dev->actionPlan)->items ?? collect();

            return [
                'id' => $dev->id,
                'type' => $typeLower, // 'forecast' | 'sales' (Ist)
                'clientName' => $dev->client_name ?: null,
                'pcCode' => $dev->profit_center_code,
                'pcName' => $dev->pc_name ?: optional($dev->profitCenter)->profit_center_name,
                // mostramos período por created_at (lo que pediste)
                'year' => (int)($dev->created_at?->format('Y') ?? $dev->fiscal_year ?? now()->year),
                'month' => (int)($dev->created_at?->format('n') ?? $dev->month ?? now()->month),

                'sales' => (float)$sales,
                'budget' => (float)$budget,
                'forecast' => (float)$fcst,
                'deltaAbs' => is_null($deltaAbs) ? 0.0 : (float)$deltaAbs,
                'deltaPct' => is_null($deltaPct) ? 0.0 : (float)$deltaPct,

                'comment' => $dev->justification->comment ?? '',
                'plan' => $dev->justification->plan ?? null, // solo forecast
                'actions' => $items->map(function ($it) {
                    return [
                        'title' => $it->title,
                        'desc'  => $it->description,
                        'due'   => $it->due_date ? $it->due_date->format('Y-m-d') : null,
                    ];
                })->values(),
                'justified' => (bool)$dev->justified,

                // opcional: series si las tenés
                'months' => $dev->months,
                'salesSeries' => $dev->sales_series,
                'budgetSeries' => $dev->budget_series,
                'forecastSeries' => $dev->forecast_series,
            ];
        });

        return response()->json($deviations->values());
    }

    public function justify(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $dev = Deviation::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        $v = Validator::make($request->all(), [
            'comment' => ['nullable', 'string'],
            'explanation' => ['nullable', 'string'], // compat
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

        // 1) Upsert justification (1:1)
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