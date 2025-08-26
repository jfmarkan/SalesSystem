<?php

namespace App\Http\Controllers;

use App\Models\ExtraQuotaAssignment;
use App\Models\SalesOpportunity;
use App\Models\ExtraQuotaBudget;
use App\Models\ExtraQuotaForecast;
use App\Models\Seasonality;

use App\Models\Client;
use App\Models\ClientProfitCenter;

use App\Models\TeamMember;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExtraQuotaController extends Controller
{
    private function pcNameSubquery()
    {
        // Si hay tabla profit_centers (code, name), la usamos
        if (Schema::hasTable('profit_centers')) {
            return 'profit_centers';
        }
        return null;
    }

    public function showOpportunityGroup(Request $request, int $groupId)
{
    $uid = $request->user()->id;

    // última versión del grupo del usuario
    $latest = DB::table('sales_opportunities')
        ->where('opportunity_group_id', $groupId)
        ->where('user_id', $uid)
        ->orderByDesc('version')
        ->first();

    if (!$latest) {
        abort(404, 'Opportunity not found');
    }

    $versions = DB::table('sales_opportunities')
        ->where('opportunity_group_id', $groupId)
        ->where('user_id', $uid)
        ->orderBy('version')
        ->get(['version']);

    return response()->json([
        'latest' => [
            'id'                   => (int)$latest->id,
            'user_id'              => (int)$latest->user_id,
            'fiscal_year'          => (int)$latest->fiscal_year,
            'profit_center_code'   => (int)$latest->profit_center_code, // numérico
            'volume'               => (int)$latest->volume,
            'probability_pct'      => (int)$latest->probability_pct,
            'estimated_start_date' => $latest->estimated_start_date,
            'comments'             => $latest->comments,
            'status'               => $latest->status,
            'potential_client_name'=> $latest->potential_client_name,
            'client_group_number'  => $latest->client_group_number,
            'updated_at'           => $latest->updated_at,
            'version'              => (int)$latest->version,
            'opportunity_group_id' => (int)$groupId,
        ],
        'versions' => $versions,
    ]);
}

public function myProfitCenters(Request $request)
{
    $userId = $request->user()->id;

    $fyRaw = $request->query('fiscal_year', $request->query('fy', $request->query('year', $request->query('fiscal-ir'))));
    $fy = $fyRaw ? (int)preg_replace('/\D+/', '', (string)$fyRaw) : null;

    $q = DB::table('extra_quota_assignments as a')
    ->where('a.user_id', $userId)
    ->where('a.is_published', true)
    ->select('a.profit_center_code', DB::raw('SUM(a.volume) as assigned_volume'));

    if ($fy) $q->where('a.fiscal_year', $fy);

    $rows = $q->groupBy('a.profit_center_code')
              ->orderBy('a.profit_center_code')
              ->get()
              ->map(function ($r) {
                  // Solo dígitos
                  $r->profit_center_code = preg_replace('/\D+/', '', (string)$r->profit_center_code);
                  return $r;
              });

    return response()->json($rows);
}


public function myVolume(Request $request)
{
    $userId = $request->user()->id;
    $code   = (string)$request->query('profit_center_code', '');

    $fyRaw = $request->query('fiscal_year', $request->query('fy', $request->query('year', $request->query('fiscal-ir'))));
    $fy = $fyRaw ? (int)preg_replace('/\D+/', '', (string)$fyRaw) : null;

    if ($code === '' || !$fy) {
        return response()->json(['assigned_volume' => 0]);
    }

    $sum = DB::table('extra_quota_assignments') // PLURAL
        ->where('user_id', $userId)
        ->where('is_published', true)
        ->where('profit_center_code', $code)
        ->where('fiscal_year', $fy)
        ->sum('volume');

    return response()->json(['assigned_volume' => (int)$sum]);
}

public function myAvailability(Request $request)
{
    $userId = $request->user()->id;
    $code   = (string)$request->query('profit_center_code', '');
    $fyRaw  = $request->query('fiscal_year', $request->query('fy', $request->query('year', $request->query('fiscal-ir'))));
    $fy     = $fyRaw ? (int)preg_replace('/\D+/', '', (string)$fyRaw) : null;

    if ($code === '' || !$fy) {
        return response()->json(['assigned_total'=>0,'used_total'=>0,'available'=>0]);
    }

    // total asignado (suma por si hay múltiples filas)
    $assigned = (int) DB::table('extra_quota_assignments')
        ->where('user_id', $userId)
        ->where('is_published', true)
        ->where('profit_center_code', $code)
        ->where('fiscal_year', $fy)
        ->sum('volume');

    // última versión por grupo del usuario en ese PC/FY
    $latestPerGroup = DB::table('sales_opportunities as s')
        ->select('s.opportunity_group_id', DB::raw('MAX(s.version) as max_version'))
        ->where('s.user_id', $userId)
        ->where('s.fiscal_year', $fy)
        ->where('s.profit_center_code', $code)
        ->groupBy('s.opportunity_group_id');

    // usado = SUM(volume) sólo de estados Open o Won (como Access)
    $used = (int) DB::table('sales_opportunities as s')
        ->joinSub($latestPerGroup, 'lv', function ($join) {
            $join->on('lv.opportunity_group_id', '=', 's.opportunity_group_id')
                 ->on('lv.max_version', '=', 's.version');
        })
        ->whereIn('s.status', ['open','won'])
        ->sum('s.volume');

    $available = max(0, $assigned - $used);

    return response()->json([
        'assigned_total' => $assigned,
        'used_total'     => $used,
        'available'      => $available,
    ]);
}

public function indexOpportunities(Request $request)
{
    $userId = $request->user()->id;
    $status = strtolower((string)$request->query('status', '')); // optional server-side filter

    $mv = DB::table('sales_opportunities')
        ->select('opportunity_group_id', DB::raw('MAX(version) AS max_version'))
        ->where('user_id', $userId)
        ->groupBy('opportunity_group_id');

    $rows = DB::table('sales_opportunities AS s')
        ->joinSub($mv, 'mv', function ($join) {
            $join->on('s.opportunity_group_id', '=', 'mv.opportunity_group_id')
                 ->on('s.version', '=', 'mv.max_version');
        })
        ->leftJoin('profit_centers AS pc', 'pc.profit_center_code', '=', 's.profit_center_code')
        ->when(in_array($status, ['open','won','lost']), function($q) use ($status){
            $q->where('s.status', $status);
        })
        ->select([
            's.opportunity_group_id',
            's.version',
            's.potential_client_name',
            's.profit_center_code',
            's.probability_pct',
            's.volume',
            's.status',
            's.updated_at',
            DB::raw("COALESCE(pc.profit_center_name, s.profit_center_code) AS profit_center_name"),
        ])
        ->orderByDesc('s.updated_at')
        ->orderByDesc('s.id')
        ->get();

    return response()->json($rows);
}

    public function createOpportunity(Request $request)
    {
        $uid = $request->user()->id;

        $data = $request->validate([
            'fiscal_year'          => 'required|integer',
            'profit_center_code'   => 'required|integer|min:0|max:65535',
            'volume'               => 'required|integer|min:0',
            'probability_pct'      => 'required|integer|min:0|max:100',
            'estimated_start_date' => 'nullable|date',
            'comments'             => 'nullable|string',
            'potential_client_name'=> 'nullable|string|max:255',
            'client_group_number'  => 'nullable|string|max:100',
            'status'               => 'nullable|in:draft,open,won,lost',
        ]);

        return DB::transaction(function () use ($uid, $data) {
            $rec = SalesOpportunity::create([
                'user_id'              => $uid,
                'fiscal_year'          => $data['fiscal_year'],
                'profit_center_code'   => $data['profit_center_code'],
                'volume'               => $data['volume'],
                'probability_pct'      => $data['probability_pct'],
                'estimated_start_date' => $data['estimated_start_date'] ?? null,
                'comments'             => $data['comments'] ?? null,
                'status'               => $data['status'] ?? 'open',
                'version'              => 1,
                'potential_client_name'=> $data['potential_client_name'] ?? null,
                'client_group_number'  => $data['client_group_number'] ?? null,
            ]);

            $rec->opportunity_group_id = $rec->id;
            $rec->save();

            return response()->json([
                'opportunity_group_id' => $rec->opportunity_group_id,
                'version' => 1,
                'id' => $rec->id,
            ]);
        });
    }

    public function createVersion(Request $request, int $groupId)
    {
        $uid = $request->user()->id;

        $data = $request->validate([
            'fiscal_year'          => 'required|integer',
            'profit_center_code'   => 'required|integer|min:0|max:65535',
            'volume'               => 'required|integer|min:0',
            'probability_pct'      => 'required|integer|min:0|max:100',
            'estimated_start_date' => 'nullable|date',
            'comments'             => 'nullable|string',
            'potential_client_name'=> 'nullable|string|max:255',
            'client_group_number'  => 'nullable|string|max:100',
            'status'               => 'nullable|in:draft,open,won,lost',
        ]);

        $maxVerUser = SalesOpportunity::where('opportunity_group_id', $groupId)
            ->where('user_id', $uid)->max('version');

        if (!$maxVerUser) abort(403, 'Not allowed or group not found');

        $newVer = $maxVerUser + 1;

        $rec = SalesOpportunity::create([
            'user_id'              => $uid,
            'opportunity_group_id' => $groupId,
            'version'              => $newVer,
            'fiscal_year'          => $data['fiscal_year'],
            'profit_center_code'   => $data['profit_center_code'],
            'volume'               => $data['volume'],
            'probability_pct'      => $data['probability_pct'],
            'estimated_start_date' => $data['estimated_start_date'] ?? null,
            'comments'             => $data['comments'] ?? null,
            'status'               => $data['status'] ?? 'open',
            'potential_client_name'=> $data['potential_client_name'] ?? null,
            'client_group_number'  => $data['client_group_number'] ?? null,
        ]);

        return response()->json(['version' => $newVer, 'id' => $rec->id]);
    }

    public function getBudget(Request $request, int $groupId, int $version)
    {
        $uid = $request->user()->id;
        $exists = SalesOpportunity::where('opportunity_group_id', $groupId)
            ->where('user_id', $uid)->exists();
        if (!$exists) abort(403, 'Not allowed');

        $rows = ExtraQuotaBudget::where([
            'opportunity_group_id' => $groupId,
            'version' => $version,
        ])->orderBy('fiscal_year')->orderBy('month')->get();

        return response()->json($rows);
    }

    public function saveBudget(Request $request, int $groupId, int $version)
    {
        $uid = $request->user()->id;
        $exists = SalesOpportunity::where('opportunity_group_id', $groupId)
            ->where('user_id', $uid)->exists();
        if (!$exists) abort(403, 'Not allowed');

        $items = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.month' => 'required|integer|min:1|max:12',
            'items.*.fiscal_year' => 'required|integer',
            'items.*.volume' => 'required|integer|min:0',
        ])['items'];

        DB::transaction(function () use ($groupId, $version, $items) {
            foreach ($items as $it) {
                ExtraQuotaBudget::updateOrCreate(
                    [
                        'opportunity_group_id' => $groupId,
                        'version' => $version,
                        'fiscal_year' => $it['fiscal_year'],
                        'month' => $it['month'],
                    ],
                    [
                        'volume' => $it['volume'],
                        'calculation_date' => now(),
                    ]
                );
            }
        });

        return response()->json(['status' => 'ok', 'saved' => count($items)]);
    }

    public function getForecast(Request $request, int $groupId, int $version)
    {
        $uid = $request->user()->id;
        $exists = SalesOpportunity::where('opportunity_group_id', $groupId)
            ->where('user_id', $uid)->exists();
        if (!$exists) abort(403, 'Not allowed');

        $rows = ExtraQuotaForecast::where([
            'opportunity_group_id' => $groupId,
            'version' => $version,
        ])->orderBy('fiscal_year')->orderBy('month')->get();

        return response()->json($rows);
    }

    public function saveForecast(Request $request, int $groupId, int $version)
    {
        $uid = $request->user()->id;
        $exists = SalesOpportunity::where('opportunity_group_id', $groupId)
            ->where('user_id', $uid)->exists();
        if (!$exists) abort(403, 'Not allowed');

        $items = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.month' => 'required|integer|min:1|max:12',
            'items.*.fiscal_year' => 'required|integer',
            'items.*.volume' => 'required|integer|min:0',
        ])['items'];

        DB::transaction(function () use ($groupId, $version, $items, $uid) {
            foreach ($items as $it) {
                ExtraQuotaForecast::updateOrCreate(
                    [
                        'opportunity_group_id' => $groupId,
                        'version' => $version,
                        'fiscal_year' => $it['fiscal_year'],
                        'month' => $it['month'],
                    ],
                    [
                        'volume' => $it['volume'],
                        'created_by' => $uid,
                    ]
                );
            }
        });

        return response()->json(['status' => 'ok', 'saved' => count($items)]);
    }

    public function seasonality(Request $request)
{
    $code = (int) $request->query('profit_center_code', 0);
    $fy   = (int) $request->query('fiscal_year', 0);

    if ($code === '') {
        return response()->json(['error' => 'profit_center_code is required'], 422);
    }

    $row = null;
    if ($fy > 0) {
        $row = Seasonality::where('profit_center_code', $code)
            ->where('fiscal_year', $fy)
            ->first();
    }
    if (!$row) {
        $row = Seasonality::where('profit_center_code', $code)
            ->orderByDesc('fiscal_year')
            ->first();
    }
    if (!$row) {
        return response()->json([
            'profit_center_code' => $code,
            'fiscal_year'        => $fy ?: (int) date('Y'),
            'weights'            => array_fill(0, 12, 1.0),
        ]);
    }

    $weights = [
        (float) $row->apr, (float) $row->may, (float) $row->jun,
        (float) $row->jul, (float) $row->aug, (float) $row->sep,
        (float) $row->oct, (float) $row->nov, (float) $row->dec,
        (float) $row->jan, (float) $row->feb, (float) $row->mar,
    ];

    return response()->json([
        'profit_center_code' => $row->profit_center_code,
        'fiscal_year'        => (int) $row->fiscal_year,
        'weights'            => $weights,
    ]);
}

    public function finalizeOpportunity(Request $req, int $group, int $version)
    {
        $uid    = $req->user()->id;
        $status = strtolower((string)$req->input('status')); // 'won' | 'lost'
        $cgn    = trim((string) $req->input('client_group_number', ''));

        if (!in_array($status, ['won','lost'])) {
            abort(422, 'invalid status');
        }

        return DB::transaction(function() use ($status, $cgn, $group, $version, $uid, $req) {
            $op = DB::table('sales_opportunities')
                ->where('opportunity_group_id', $group)
                ->where('version', $version)
                ->where('user_id', $uid)
                ->lockForUpdate()->first();

            if (!$op) abort(404, 'Opportunity version not found');

            if ($status === 'lost') {
                DB::table('sales_opportunities')
                ->where('opportunity_group_id',$group)
                ->where('version',$version)
                ->update(['status'=>'lost']);
                return response()->noContent();
            }

            // WON
            // WON
if ($cgn === '') abort(422, 'client_group_number required');

$clientName = (string) $req->input('client_name', $op->potential_client_name ?? '—');

// Cliente PK = client_group_number; default classification_id = 2
$client = Client::withTrashed()->find((int)$cgn);
if (!$client) {
    $client = Client::create([
        'client_group_number' => (int)$cgn,
        'client_name'         => $clientName,
        'classification_id'   => 2,
    ]);
} elseif ($client->classification_id === null) {
    $client->classification_id = 2;
    $client->save();
}

// Vínculo cliente–profit center (client_profit_centers)
$cpc = ClientProfitCenter::withTrashed()->firstOrCreate([
    'client_group_number' => (int)$cgn,
    'profit_center_code'  => (int)$op->profit_center_code,
]);
if (method_exists($cpc, 'restore') && $cpc->trashed()) {
    $cpc->restore();
}

// TeamID desde team_members por user_id
$teamId = TeamMember::query()
    ->where('user_id', $op->user_id)
    ->orderByDesc('updated_at')
    ->value('team_id');
if (!$teamId) abort(422, 'team not found for user');

// Assignment SIN fiscal_year
$assignId = DB::table('assignments')->insertGetId([
    'client_profit_center_id' => $cpc->id, // = SID
    'team_id'                 => $teamId,
    'user_id'                 => $op->user_id,
    'created_at'              => now(),
    'updated_at'              => now(),
]);

// Mover budget/forecast extra → permanentes
$bud = DB::table('extra_quota_budgets')
    ->where('opportunity_group_id',$group)->where('version',$version)->get();
foreach ($bud as $r) {
    DB::table('budgets')->insert([
        'client_profit_center_id' => $cpc->id,
        'fiscal_year'             => $r->fiscal_year,
        'month'                   => $r->month,
        'volume'                  => $r->volume,
        'created_at'              => now(),
        'updated_at'              => now(),
    ]);
}

$fc = DB::table('extra_quota_forecasts')
    ->where('opportunity_group_id',$group)->where('version',$version)->get();
foreach ($fc as $r) {
    DB::table('forecasts')->insert([
        'client_profit_center_id' => $cpc->id,
        'fiscal_year'             => $r->fiscal_year,
        'month'                   => $r->month,
        'volume'                  => $r->volume,
        'user_id'                 => $op->user_id,
        'created_at'              => now(),
        'updated_at'              => now(),
    ]);
}

// Marcar ganada y setear CGN
DB::table('sales_opportunities')
  ->where('opportunity_group_id',$group)
  ->where('version',$version)
  ->update(['status'=>'won', 'client_group_number'=>$cgn]);

// Limpiar extra
DB::table('extra_quota_budgets')->where('opportunity_group_id',$group)->where('version',$version)->delete();
DB::table('extra_quota_forecasts')->where('opportunity_group_id',$group)->where('version',$version)->delete();

return response()->noContent();

        });
    }
}