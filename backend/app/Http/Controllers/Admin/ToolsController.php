<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// existentes
use App\Services\BudgetingService;
use App\Services\ForecastingService;

// nuevos
use App\Services\ClientsSynchronizer;
use App\Jobs\ClientsUpdateJob;

class ToolsController extends Controller
{
    public function rebuildSales(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date_format:Y-m-d'
        ]);
        //RebuildSalesFromDateJob::dispatch($data['start_date'], $request->user()->id);
        return response()->json(['queued' => true], 202);
    }

    public function generateBudget(Request $request, BudgetingService $svc)
    {
        $data = $request->validate([
            'fiscal_year' => 'required|integer',
            'full_rebuild' => 'boolean',
            'cutoff_month' => 'nullable|integer|min:1|max:12',
        ]);
        $res = $svc->generate(
            $data['fiscal_year'],
            (bool)($data['full_rebuild'] ?? false),
            $data['cutoff_month'] ?? null
        );
        return response()->json($res);
    }

    // Si más adelante agregás ruta POST /api/settings/tools/generate-forecast
    public function generateForecast(Request $request, ForecastingService $svc)
    {
        $data = $request->validate([
            'fiscal_year' => 'required|integer',
            'version'     => 'nullable|integer|min:1',
            'overwrite'   => 'boolean',
        ]);
        $res = $svc->generateFromBudgets(
            $data['fiscal_year'],
            (int)($data['version'] ?? 1),
            (bool)($data['overwrite'] ?? false),
        );
        return response()->json($res);
    }

    public function setBudgetSeason(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'required|boolean'
        ]);
        Cache::forever('flags:budget-season', (bool)$data['enabled']);
        return response()->json(['ok' => true, 'enabled' => (bool)$data['enabled']]);
    }

    // App\Http\Controllers\Admin\ToolsAdminController.php
    public function clientsUpdate(Request $request, \App\Services\ClientsSynchronizer $svc)
    {
        $data  = $request->validate(['queued' => 'sometimes|boolean','dry_run' => 'sometimes|boolean']);
        $apply = !$request->boolean('dry_run', false); // GET dry-run si quieres, POST aplica

        if ($request->boolean('queued', false)) {
            \App\Jobs\ClientsUpdateJob::dispatch(['apply' => $apply]);
            return response()->json(['queued' => true], 202);
        }

        return response()->json($svc->clientsUpdate(['apply' => $apply]));
    }
}
