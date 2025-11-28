<?php

namespace App\Http\Controllers;

use App\Models\BudgetCase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetCaseController extends Controller
{
    public function index(Request $request)
    {
        $v = $request->validate([
            'client_profit_center_id' => ['nullable','integer','min:1'],
            'client_group_number'     => ['nullable','integer'],
            'profit_center_code'      => ['nullable','integer'],
            'fiscal_year'             => ['required','integer','min:1900'],
        ]);

        // resolve CPC id
        $cpcId = $v['client_profit_center_id'] ?? null;
        if (!$cpcId) {
            if (!isset($v['client_group_number'], $v['profit_center_code'])) {
                return response()->json(['message' => 'Missing identifiers.'], 422);
            }
            $cpcId = DB::table('client_profit_centers')
                ->where('client_group_number', (int)$v['client_group_number'])
                ->where('profit_center_code', (int)$v['profit_center_code'])
                ->value('id');
            if (!$cpcId) {
                // No CPC mapping -> no hay caso
                return response()->json(['data' => null]);
            }
        }

        $case = BudgetCase::query()
            ->where('client_profit_center_id', (int)$cpcId)
            ->where('fiscal_year', (int)$v['fiscal_year'])
            ->first();

        return response()->json([
            'data' => $case ?: null,
        ]);
    }

    /**
     * GET /api/budget-cases/exists?fiscal_year=2026&cpc_ids=1,2,3
     * Returns: { exists: [1,3] }
     */
    public function exists(Request $request)
    {
        $v = $request->validate([
            'fiscal_year' => ['required','integer','min:1900'],
            'cpc_ids'     => ['required','string'], // comma-separated
        ]);

        $ids = collect(explode(',', $v['cpc_ids']))
            ->map(fn($x) => (int)$x)
            ->filter(fn($x) => $x > 0)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['exists' => []]);
        }

        $found = BudgetCase::query()
            ->whereIn('client_profit_center_id', $ids)
            ->where('fiscal_year', (int)$v['fiscal_year'])
            ->pluck('client_profit_center_id')
            ->map(fn($x) => (int)$x)
            ->values();

        return response()->json(['exists' => $found]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // path A: direct CPC id (optional)
            'client_profit_center_id' => ['nullable','integer','exists:client_profit_centers,id'],

            // path B: resolve CPC by CGN + PCC (optional)
            'client_group_number'     => ['nullable','integer'],
            'profit_center_code'      => ['nullable','integer'],

            // values to save
            'best_case'               => ['required','numeric','between:-100,100'],
            'worst_case'              => ['required','numeric','between:-100,100'],
            'skip_budget'             => ['nullable','boolean'],

            // optional override for FY
            'fiscal_year'             => ['nullable','integer','min:1900'],
        ]);

        // 1) Resolve CPC id
        $cpcId = $data['client_profit_center_id'] ?? null;

        if (!$cpcId) {
            // need CGN + PCC
            if (!isset($data['client_group_number'], $data['profit_center_code'])) {
                return response()->json([
                    'message' => 'Missing client_profit_center_id or (client_group_number + profit_center_code).'
                ], 422);
            }

            $cpcId = DB::table('client_profit_centers')
                ->where('client_group_number', (int)$data['client_group_number'])
                ->where('profit_center_code', (int)$data['profit_center_code'])
                ->value('id');

            if (!$cpcId) {
                return response()->json([
                    'message' => 'Client-Profit-Center not found for given client_group_number and profit_center_code.'
                ], 422);
            }
        }

        // 2) Fiscal year = current year + 1 if not provided
        $fy = isset($data['fiscal_year']) ? (int)$data['fiscal_year'] : (int) Carbon::now()->year + 1;

        // 3) Upsert
        $case = BudgetCase::updateOrCreate(
            [
                'client_profit_center_id' => (int)$cpcId,
                'fiscal_year'             => $fy,
            ],
            [
                'best_case'  => (float)$data['best_case'],
                'worst_case' => (float)$data['worst_case'],
                'skip_budget' => (bool) ($data['skip_budget'] ?? false),
            ]
        );

        return response()->json([
            'message' => 'Budget Case gespeichert.',
            'data'    => $case,
        ]);
    }
}
