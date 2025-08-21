<?php
// app/Http/Controllers/BudgetCaseController.php

namespace App\Http\Controllers;

use App\Models\BudgetCase;
use Illuminate\Http\Request;

class BudgetCaseController extends Controller
{
    // Store/Update user case percentages (Best/Worst)
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_profit_center_id' => ['required','integer','exists:client_profit_centers,id'],
            'fiscal_year'             => ['required','integer','min:1900'],
            'best_case'               => ['required','numeric','between:-100,100'],
            'worst_case'              => ['required','numeric','between:-100,100'],
        ]);

        $case = BudgetCase::updateOrCreate(
            [
                'client_profit_center_id' => $data['client_profit_center_id'],
                'fiscal_year'             => $data['fiscal_year'],
            ],
            [
                'best_case'               => $data['best_case'],
                'worst_case'              => $data['worst_case'],
            ]
        );

        return response()->json([
            'message' => 'Budget Case gespeichert.',
            'data'    => $case,
        ]);
    }

    // Retrieve a case by CPC + FY (for prefill/simulation)
    public function show(Request $request)
    {
        $request->validate([
            'client_profit_center_id' => ['required','integer','exists:client_profit_centers,id'],
            'fiscal_year'             => ['required','integer'],
        ]);

        $case = BudgetCase::where('client_profit_center_id', $request->client_profit_center_id)
            ->where('fiscal_year', $request->fiscal_year)
            ->first();

        return response()->json([
            'data' => $case,
        ]);
    }

    // Optional: list all cases for a CPC
    public function index(Request $request)
    {
        $request->validate([
            'client_profit_center_id' => ['required','integer','exists:client_profit_centers,id'],
        ]);

        $items = BudgetCase::where('client_profit_center_id', $request->client_profit_center_id)
            ->orderBy('fiscal_year','desc')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    // Optional: delete a case (keeps budgets untouched)
    public function destroy(Request $request)
    {
        $request->validate([
            'client_profit_center_id' => ['required','integer','exists:client_profit_centers,id'],
            'fiscal_year'             => ['required','integer'],
        ]);

        BudgetCase::where('client_profit_center_id', $request->client_profit_center_id)
            ->where('fiscal_year', $request->fiscal_year)
            ->delete();

        return response()->json([
            'message' => 'Budget Case gelöscht.',
        ]);
    }
}