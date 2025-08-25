<?php

namespace App\Http\Controllers;

use App\Models\ExtraQuotaAssignment;
use App\Models\SalesOpportunity;
use App\Models\ExtraQuotaBudget;
use App\Models\ExtraQuotaForecast;
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

public function myProfitCenters(Request $request)
{
    $userId = $request->user()->id;

    // Tolerante con nombres de parámetro: fiscal_year | fy | year | fiscal-ir
    $fyRaw = $request->query('fiscal_year', $request->query('fy', $request->query('year', $request->query('fiscal-ir'))));
    $fy = $fyRaw ? (int)preg_replace('/\D+/', '', (string)$fyRaw) : null;

    $q = DB::table('extra_quota_assignment as a')
        ->join('profit_centers as pc', 'pc.profit_center_code', '=', 'a.profit_center_code')
        ->where('a.user_id', $userId)
        ->where('a.is_published', true)
        ->select(
            'a.profit_center_code',
            'pc.profit_center_name',
            DB::raw('SUM(a.volume) as assigned_volume'),     // ← volume correcto
            DB::raw('MAX(a.is_published) as is_published')   // boolean agregado por si lo querés ver
        );

    if ($fy) {
        $q->where('a.fiscal_year', $fy);
    }

    $rows = $q->groupBy('a.profit_center_code', 'pc.profit_center_name')
              ->orderBy('pc.profit_center_name')
              ->get();

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

    $sum = DB::table('extra_quota_assignment')
        ->where('user_id', $userId)
        ->where('is_published', true)
        ->where('profit_center_code', $code)
        ->where('fiscal_year', $fy)
        ->sum('volume'); // ← volume correcto

    return response()->json(['assigned_volume' => (int)$sum]);
}

public function myAvailability(Request $request)
{
    $userId = $request->user()->id;
    $code   = (string)$request->query('profit_center_code', '');
    $fyRaw  = $request->query('fiscal_year', $request->query('fy', $request->query('year', $request->query('fiscal-ir'))));
    $fy     = $fyRaw ? (int)preg_replace('/\D+/', '', (string)$fyRaw) : null;

    if ($code === '' || !$fy) {
        return response()->json([
            'assigned_total' => 0,
            'used_total'     => 0,
            'available'      => 0,
        ]);
    }

    // 1) total asignado (solo publicados)
    $assigned = (int) DB::table('extra_quota_assignment')
        ->where('user_id', $userId)
        ->where('is_published', true)
        ->where('profit_center_code', $code)
        ->where('fiscal_year', $fy)
        ->sum('volume'); // <- volume correcto

    // 2) volumen usado = suma del volume de la ÚLTIMA versión por grupo (del mismo user/PC/FY)
    $latestPerGroup = DB::table('sales_opportunities as s')
        ->select('s.opportunity_group_id', DB::raw('MAX(s.version) as max_version'))
        ->where('s.user_id', $userId)
        ->where('s.fiscal_year', $fy)
        ->where('s.profit_center_code', $code)
        ->groupBy('s.opportunity_group_id');

    $used = (int) DB::table('sales_opportunities as s')
        ->joinSub($latestPerGroup, 'lv', function ($join) {
            $join->on('lv.opportunity_group_id', '=', 's.opportunity_group_id')
                 ->on('lv.max_version', '=', 's.version');
        })
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

        // última versión por grupo del usuario
        $mv = DB::table('sales_opportunities')
            ->select('opportunity_group_id', DB::raw('MAX(version) AS max_version'))
            ->where('user_id', $userId)
            ->groupBy('opportunity_group_id');

        // filas de última versión + nombre de PC
        $rows = DB::table('sales_opportunities AS s')
            ->joinSub($mv, 'mv', function ($join) {
                $join->on('s.opportunity_group_id', '=', 'mv.opportunity_group_id')
                    ->on('s.version', '=', 'mv.max_version');
            })
            ->leftJoin('profit_centers AS pc', function ($join) {
                // soporta pc.code o pc.profit_center_code
                $join->on('pc.code', '=', 's.profit_center_code')
                    ->orOn('pc.profit_center_code', '=', 's.profit_center_code');
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
                DB::raw("COALESCE(pc.profit_center_name, pc.name, s.profit_center_code) AS profit_center_name"),
            ])
            ->orderByDesc('s.updated_at')
            ->orderByDesc('s.id')
            ->get();

        return response()->json($rows);
    }

    public function showOpportunityGroup(Request $request, int $groupId)
    {
        $uid = $request->user()->id;

        $maxVer = SalesOpportunity::where('opportunity_group_id', $groupId)
            ->where('user_id', $uid)->max('version');

        if (!$maxVer) abort(404, 'Opportunity not found');

        $latest = SalesOpportunity::where('opportunity_group_id', $groupId)
            ->where('user_id', $uid)
            ->where('version', $maxVer)
            ->firstOrFail();

        $versions = SalesOpportunity::where('opportunity_group_id', $groupId)
            ->where('user_id', $uid)
            ->orderBy('version')
            ->get(['version']);

        return response()->json([
            'latest'   => $latest,
            'versions' => $versions,
        ]);
    }

    public function createOpportunity(Request $request)
    {
        $uid = $request->user()->id;

        $data = $request->validate([
            'fiscal_year'          => 'required|integer',
            'profit_center_code'   => 'required|string|max:50',
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
            'profit_center_code'   => 'required|string|max:50',
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
}