<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// existentes
use App\Services\BudgetingService;
use App\Services\ForecastingService;
use App\Models\ToolRun;

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

    public function clientsUpdate(Request $request, \App\Services\ClientsSynchronizer $svc)
    {
        $data  = $request->validate(['queued' => 'sometimes|boolean','dry_run' => 'sometimes|boolean']);
        $apply = !$request->boolean('dry_run', false);
        $queued = $request->boolean('queued', true);

        $run = ToolRun::create([
            'tool'   => 'clients-update',
            'status' => $queued ? 'queued' : 'running',
            'user_id'=> optional($request->user())->id,
            'options'=> ['apply' => $apply],
            'log'    => '',
        ]);

        if ($queued) {
            \App\Jobs\ClientsUpdateJob::dispatch($run->id, ['apply' => $apply]);
            return response()->json(['ok' => true, 'queued' => true, 'run_id' => $run->id], 202);
        }

        $logger = function (string $line) use ($run) { $run->appendLog($line); };
        try {
            $run->started_at = now(); $run->save();
            $res = $svc->clientsUpdate(['apply' => $apply], $logger);
            $run->status = 'ok';
            $run->stats = $res['summary'] ?? [];
            $run->finished_at = now();
            $run->save();
            return response()->json(['ok' => true, 'queued' => false, 'run_id' => $run->id, 'summary' => $run->stats]);
        } catch (\Throwable $e) {
            $run->appendLog('ERROR: '.$e->getMessage());
            $run->status = 'failed';
            $run->finished_at = now();
            $run->save();
            return response()->json(['ok' => false, 'run_id' => $run->id, 'message' => $e->getMessage()], 500);
        }
    }

    public function toolRunsIndex(Request $request)
    {
        $rows = ToolRun::query()
            ->where('tool', 'clients-update')
            ->orderByDesc('id')
            ->limit((int)$request->integer('limit', 20))
            ->get(['id','tool','status','created_at','started_at','finished_at','stats','options','user_id']);
        return response()->json(['items' => $rows]);
    }

    public function toolRunsShow(int $id)
    {
        $row = ToolRun::findOrFail($id);
        return response()->json([
            'id' => $row->id,
            'tool' => $row->tool,
            'status' => $row->status,
            'stats' => $row->stats,
            'options' => $row->options,
            'created_at' => $row->created_at,
            'started_at' => $row->started_at,
            'finished_at' => $row->finished_at,
            'log' => $row->log,
        ]);
    }
}
