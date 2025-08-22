<?php
// app/Http/Controllers/BudgetCaseController.php

namespace App\Http\Controllers;

use App\Models\BudgetCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetCaseController extends Controller
{
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
            ]
        );

        return response()->json([
            'message' => 'Budget Case gespeichert.',
            'data'    => $case,
        ]);
    }
}