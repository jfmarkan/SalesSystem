<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    private function fyOf(\DateTimeInterface $d): int
    {
        $y = (int)$d->format('Y');
        $m = (int)$d->format('n');
        return $m >= 4 ? $y : $y - 1;
    }

    public function index(Request $request)
    {
        $fy = (int)($request->input('fiscal_year') ?: $this->fyOf(now()));

        $totalCpc = DB::table('client_profit_centers')->count();

        $budgetCases = DB::table('budget_cases')
            ->where('fiscal_year', $fy)
            ->count();

        $budgetsMonths = DB::table('budgets')
            ->where('fiscal_year', $fy)
            ->count(); // registros mensuales (cpc * meses presentes)

        $forecastsMonths = DB::table('forecasts')
            ->where('fiscal_year', $fy)
            ->count();

        $coverageBudgetCases = $totalCpc > 0 ? round($budgetCases / $totalCpc * 100, 2) : 0;

        return response()->json([
            'fiscal_year' => $fy,
            'totals' => [
                'client_profit_centers' => $totalCpc,
            ],
            'budgeting' => [
                'cases' => $budgetCases,
                'coverage_pct' => $coverageBudgetCases,
                'budget_rows' => $budgetsMonths,
            ],
            'forecasting' => [
                'forecast_rows' => $forecastsMonths,
            ],
        ]);
    }
}