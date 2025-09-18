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
use Illuminate\Support\Str;

class ExtraQuotaController extends Controller
{
    private function pcNameSubquery()
    {
        if (Schema::hasTable('profit_centers')) return 'profit_centers';
        return null;
    }

    /** Mark older versions as DRAFT (opportunity + extra tables if they have 'status') */
    private function markOlderVersionsAsDraft(int $groupId): void
    {
        $now = now();

        if (Schema::hasColumn('sales_opportunities', 'status')) {
            DB::table('sales_opportunities')
                ->where('opportunity_group_id', $groupId)
                ->where('status', 'open')
                ->update(['status' => 'draft', 'updated_at' => $now]);
        }
        if (Schema::hasColumn('extra_quota_forecasts', 'status')) {
            DB::table('extra_quota_forecasts')
                ->where('opportunity_group_id', $groupId)
                ->update(['status' => 'draft', 'updated_at' => $now]);
        }
        if (Schema::hasColumn('extra_quota_budgets', 'status')) {
            DB::table('extra_quota_budgets')
                ->where('opportunity_group_id', $groupId)
                ->update(['status' => 'draft', 'updated_at' => $now]);
        }
    }

    private function tableHasStatus(string $table): bool
    {
        return Schema::hasColumn($table, 'status');
    }

    public function showOpportunityGroup(Request $request, int $groupId)
    {
        $uid = $request->user()->id;

        $latest = DB::table('sales_opportunities')
            ->where('opportunity_group_id', $groupId)
            ->where('user_id', $uid)
            ->orderByDesc('version')
            ->first();
        if (!$latest) abort(404, 'Opportunity not found');

        // ⬇️ ahora también status + updated_at
        $versions = DB::table('sales_opportunities')
            ->where('opportunity_group_id', $groupId)
            ->where('user_id', $uid)
            ->orderBy('version')
            ->get(['version','status','updated_at']);

        return response()->json([
            'latest' => [
                'id'                   => (int)$latest->id,
                'user_id'              => (int)$latest->user_id,
                'fiscal_year'          => (int)$latest->fiscal_year,
                'profit_center_code'   => (int)$latest->profit_center_code,
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

        if ($code === '' || !$fy) return response()->json(['assigned_volume' => 0]);

        $sum = DB::table('extra_quota_assignments')
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

        if ($code === '' || !$fy) return response()->json(['assigned_total'=>0,'used_total'=>0,'available'=>0]);

        $assigned = (int) DB::table('extra_quota_assignments')
            ->where('user_id', $userId)
            ->where('is_published', true)
            ->where('profit_center_code', $code)
            ->where('fiscal_year', $fy)
            ->sum('volume');

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
        $status = strtolower((string)$request->query('status', ''));

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
                'status'               => $data['status'] ?? 'open', // default OPEN
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

        return DB::transaction(function() use ($groupId, $uid, $data) {
            $maxVerUser = SalesOpportunity::where('opportunity_group_id', $groupId)
                ->where('user_id', $uid)->max('version');

            if (!$maxVerUser) abort(403, 'Not allowed or group not found');

            $newVer = $maxVerUser + 1;

            // 🟡 Todas las versiones anteriores a draft
            DB::table('sales_opportunities')
                ->where('opportunity_group_id', $groupId)
                ->where('user_id', $uid)
                ->where('version', '<=', $maxVerUser)
                ->update(['status' => 'draft', 'updated_at'=> now()]);

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
                'status'               => $data['status'] ?? 'open', // solo open/draft en UI
                'potential_client_name'=> $data['potential_client_name'] ?? null,
                'client_group_number'  => $data['client_group_number'] ?? null,
            ]);

            return response()->json(['version' => $newVer, 'id' => $rec->id]);
        });
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
            $now = now();
            foreach ($items as $it) {
                $payload = [
                    'volume'           => $it['volume'],
                    'calculation_date' => $now,
                ];
                if ($this->tableHasStatus('extra_quota_budgets')) {
                    $payload['status']     = 'open';
                    $payload['updated_at'] = $now;
                }

                ExtraQuotaBudget::updateOrCreate(
                    [
                        'opportunity_group_id' => $groupId,
                        'version'     => $version,
                        'fiscal_year' => $it['fiscal_year'],
                        'month'       => $it['month'],
                    ],
                    $payload
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
            $now = now();
            foreach ($items as $it) {
                $payload = [
                    'volume'     => $it['volume'],
                    'created_by' => $uid,
                ];
                if ($this->tableHasStatus('extra_quota_forecasts')) {
                    $payload['status']     = 'open';
                    $payload['updated_at'] = $now;
                }

                ExtraQuotaForecast::updateOrCreate(
                    [
                        'opportunity_group_id' => $groupId,
                        'version'     => $version,
                        'fiscal_year' => $it['fiscal_year'],
                        'month'       => $it['month'],
                    ],
                    $payload
                );
            }
        });

        return response()->json(['status' => 'ok', 'saved' => count($items)]);
    }

    public function seasonality(Request $request)
    {
        $code = (int) $request->query('profit_center_code', 0);
        $fy   = (int) $request->query('fiscal_year', 0);

        if ($code === '') return response()->json(['error' => 'profit_center_code is required'], 422);

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

    if (!in_array($status, ['won','lost'])) abort(422, 'invalid status');

    return DB::transaction(function() use ($status, $cgn, $group, $version, $uid, $req) {
        $op = DB::table('sales_opportunities')
            ->where('opportunity_group_id', $group)
            ->where('version', $version)
            ->where('user_id', $uid)
            ->lockForUpdate()->first();
        if (!$op) abort(404, 'Opportunity version not found');

        if ($status === 'lost') {
            // 🔴 Perdida: no cuenta para nada
            DB::table('sales_opportunities')
              ->where('opportunity_group_id',$group)
              ->where('version',$version)
              ->update(['status'=>'lost','updated_at'=>now()]);
            return response()->noContent();
        }

        // 🟢 WON: mover a Budgets/Forecasts (como ya tenías)
        if ($cgn === '') abort(422, 'client_group_number required');
        $clientName = (string) $req->input('client_name', $op->potential_client_name ?? '—');
        // ... (mismo bloque de cliente + assignment + mover budgets/forecasts) ...

        // marcar la versión actual como WON
        DB::table('sales_opportunities')
          ->where('opportunity_group_id',$group)
          ->where('version',$version)
          ->update([
              'status' => 'won',
              'client_group_number' => $cgn,
              'updated_at' => now(),
          ]);

        // 🟡 todas las demás versiones del grupo a draft
        DB::table('sales_opportunities')
          ->where('opportunity_group_id',$group)
          ->where('version','<>',$version)
          ->update(['status'=>'draft','updated_at'=>now()]);

        // ⚠️ NO borramos extra_quota_*; la resta se hace en Analytics para evitar duplicación
        return response()->noContent();
    });
}

    private function fiscalYear(Request $req): int 
    {
        $raw = $req->query('fiscal_year', $req->query('fy', $req->query('year', date('Y'))));
        return (int)preg_replace('/\D+/', '', (string)$raw);
    }

    private function hasConv(): bool 
    {
        return Schema::hasTable('unit_conversions');
    }

    private function convFactorExpr(string $alias = 'uc'): string 
    {
        foreach (['factor_to_m3','factos_to_m3','to_m3_factor','factor_m3'] as $c) {
            if (Schema::hasColumn('unit_conversions', $c)) return "COALESCE($alias.$c,1)";
        }
        return '1';
    }

    private function sumAssignmentsM3(int $userId, int $fy, ?string $pcCode = null): float 
    {
        $q = DB::table('extra_quota_assignments as a')
            ->where('a.user_id', $userId)
            ->where('a.is_published', true)
            ->where('a.fiscal_year', $fy);

        if ($pcCode !== null) $q->where('a.profit_center_code', $pcCode);

        $factor = '1';
        if ($this->hasConv()) {
            $q->leftJoin('unit_conversions as uc', 'uc.profit_center_code', '=', 'a.profit_center_code');
            $factor = $this->convFactorExpr('uc');
        }

        $sql = "COALESCE(SUM(CAST(a.volume AS DECIMAL(32,8)) * ($factor)),0)";
        return (float) ($q->selectRaw("$sql AS m3")->value('m3') ?? 0.0);
    }

    private function sumOpportunitiesM3(int $userId, int $fy, array $statuses, ?string $pcCode = null): float 
    {
        $lv = DB::table('sales_opportunities')
            ->select('opportunity_group_id', DB::raw('MAX(version) AS max_version'))
            ->where('user_id', $userId)
            ->where('fiscal_year', $fy)
            ->groupBy('opportunity_group_id');

        $q = DB::table('sales_opportunities as s')
            ->joinSub($lv, 'lv', function ($j) {
                $j->on('lv.opportunity_group_id','=','s.opportunity_group_id')
                  ->on('lv.max_version','=','s.version');
            })
            ->whereIn('s.status', $statuses);

        if ($pcCode !== null) $q->where('s.profit_center_code', $pcCode);

        $factor = '1';
        if ($this->hasConv()) {
            $q->leftJoin('unit_conversions as uc', 'uc.profit_center_code', '=', 's.profit_center_code');
            $factor = $this->convFactorExpr('uc');
        }

        $sql = "COALESCE(SUM(CAST(s.volume AS DECIMAL(32,8)) * ($factor)),0)";
        return (float) ($q->selectRaw("$sql AS m3")->value('m3') ?? 0.0);
    }

    private function buildPcMixM3(int $userId, int $fy): array 
    {
        $q = DB::table('extra_quota_assignments as a')
            ->where('a.user_id', $userId)
            ->where('a.is_published', true)
            ->where('a.fiscal_year', $fy);

        $factor = '1';
        if ($this->hasConv()) {
            $q->leftJoin('unit_conversions as uc', 'uc.profit_center_code', '=', 'a.profit_center_code');
            $factor = $this->convFactorExpr('uc');
        }

        $labelExpr = "a.profit_center_code";
        if (Schema::hasTable('profit_centers') && Schema::hasColumn('profit_centers','profit_center_code')) {
            $q->leftJoin('profit_centers as pc', 'pc.profit_center_code', '=', 'a.profit_center_code');
            if (Schema::hasColumn('profit_centers','profit_center_name')) {
                $labelExpr = "CONCAT(a.profit_center_code, ' · ', COALESCE(pc.profit_center_name,''))";
            }
        }

        $rows = $q->selectRaw("$labelExpr AS label, a.profit_center_code AS code, COALESCE(SUM(CAST(a.volume AS DECIMAL(32,8)) * ($factor)),0) AS amount")
                  ->groupBy('a.profit_center_code', DB::raw($labelExpr))
                  ->orderByDesc(DB::raw("SUM(CAST(a.volume AS DECIMAL(32,8)) * ($factor))"))
                  ->get();

        $out = [];
        foreach ($rows as $i => $r) {
            $label = trim((string)($r->label ?? $r->code ?? '—'));
            $out[] = [
                'key'    => Str::slug((string)$r->code) ?: "pc-$i",
                'code'   => (string)$r->code,
                'label'  => $label === '' ? (string)$r->code : $label,
                'amount' => (float)$r->amount,
            ];
        }
        return $out;
    }

    public function portfolio(Request $req)
    {
        $user   = $req->user();
        $userId = (int)$user->id;
        $name   = (string)($user->name ?? '—');
        $fy     = $this->fiscalYear($req);

        $assigned = $this->sumAssignmentsM3($userId, $fy, null);
        $used     = $this->sumOpportunitiesM3($userId, $fy, ['open','won']);
        $mixPc    = $this->buildPcMixM3($userId, $fy);

        return response()->json([
            'title'    => 'Zusatzquoten',
            'target'   => $assigned,
            'achieved' => $used,
            'mix'      => $mixPc,
            'mix_pc'   => $mixPc,
            'items'    => [[ 'name'=>$name, 'userId'=>$userId, 'assigned'=>$assigned, 'used'=>$used ]]
        ]);
    }

    public function pcPortfolio(Request $req, string $code)
    {
        $userId   = (int)$req->user()->id;
        $fy       = $this->fiscalYear($req);

        $allocated = $this->sumAssignmentsM3($userId, $fy, $code);
        $won       = $this->sumOpportunitiesM3($userId, $fy, ['won'],  $code);
        $lost      = $this->sumOpportunitiesM3($userId, $fy, ['lost'], $code);
        $open      = max(0.0, $allocated - $won - $lost);

        return response()->json([
            'allocated' => $allocated,
            'won'       => $won,
            'lost'      => $lost,
            'open'      => $open
        ]);
    }

    public function analysisSummary(Request $req)
    {
        $userId = (int)$req->user()->id;
        $fy     = $this->fiscalYear($req);

        $hasConv = $this->hasConv();

        $qa = DB::table('extra_quota_assignments as a')
            ->where('a.user_id', $userId)
            ->where('a.is_published', true)
            ->where('a.fiscal_year', $fy);

        $factorA = '1';
        if ($hasConv) {
            $qa->leftJoin('unit_conversions as uc_a', 'uc_a.profit_center_code', '=', 'a.profit_center_code');
            $factorA = $this->convFactorExpr('uc_a');
        }
        if (Schema::hasTable('profit_centers')) {
            $qa->leftJoin('profit_centers as pc_a','pc_a.profit_center_code','=','a.profit_center_code');
        }

        $assignSub = DB::query()->fromSub(
            $qa->selectRaw("
                a.profit_center_code,
                COALESCE(SUM(CAST(a.volume AS DECIMAL(32,8)) * ($factorA)),0) AS assigned_m3,
                COALESCE(MAX(pc_a.profit_center_name), NULL) AS pc_name_assign
            ")
            ->groupBy('a.profit_center_code'),
            'A'
        );

        $lv = DB::table('sales_opportunities')
            ->select('opportunity_group_id', DB::raw('MAX(version) AS max_version'))
            ->where('user_id', $userId)
            ->where('fiscal_year', $fy)
            ->groupBy('opportunity_group_id');

        $so = DB::table('sales_opportunities as s')
            ->joinSub($lv, 'lv', function ($j) {
                $j->on('lv.opportunity_group_id','=','s.opportunity_group_id')
                  ->on('lv.max_version','=','s.version');
            })
            ->where('s.user_id', $userId)
            ->where('s.fiscal_year', $fy);

        $factorS = '1';
        if ($hasConv) {
            $so->leftJoin('unit_conversions as uc_s', 'uc_s.profit_center_code', '=', 's.profit_center_code');
            $factorS = $this->convFactorExpr('uc_s');
        }
        if (Schema::hasTable('profit_centers')) {
            $so->leftJoin('profit_centers as pc_s','pc_s.profit_center_code','=','s.profit_center_code');
        }

        $opsSub = DB::query()->fromSub(
            $so->selectRaw("
                s.profit_center_code,
                COALESCE(SUM(CASE WHEN s.status='won'
                             THEN CAST(s.volume AS DECIMAL(32,8)) * ($factorS) ELSE 0 END),0) AS converted_m3,
                COALESCE(SUM(CASE WHEN s.status='open'
                             THEN CAST(s.volume AS DECIMAL(32,8)) * (COALESCE(s.probability_pct,0)/100.0) * ($factorS) ELSE 0 END),0) AS prob_weighted_open_m3,
                COUNT(*) AS count_total,
                SUM(CASE WHEN s.status='open' THEN 1 ELSE 0 END)  AS count_open,
                SUM(CASE WHEN s.status='won'  THEN 1 ELSE 0 END)  AS count_won,
                SUM(CASE WHEN s.status='lost' THEN 1 ELSE 0 END)  AS count_lost,
                COALESCE(MAX(pc_s.profit_center_name), NULL) AS pc_name_ops
            ")
            ->groupBy('s.profit_center_code'),
            'O'
        );

        $pcSet = DB::query()->fromSub(
            DB::table('extra_quota_assignments as a')
                ->where('a.user_id',$userId)->where('a.is_published',true)->where('a.fiscal_year',$fy)
                ->select('a.profit_center_code')->groupBy('a.profit_center_code')
                ->union(
                    DB::table('sales_opportunities as s')
                        ->joinSub($lv,'lv2',function($j){
                            $j->on('lv2.opportunity_group_id','=','s.opportunity_group_id')
                              ->on('lv2.max_version','=','s.version');
                        })
                        ->where('s.user_id',$userId)->where('s.fiscal_year',$fy)
                        ->select('s.profit_center_code')->groupBy('s.profit_center_code')
                ),
            'P'
        );

        $q = $pcSet
            ->leftJoinSub($assignSub, 'A', 'A.profit_center_code','=','P.profit_center_code')
            ->leftJoinSub($opsSub,   'O', 'O.profit_center_code','=','P.profit_center_code');

        if (Schema::hasTable('profit_centers') && Schema::hasColumn('profit_centers','profit_center_name')) {
            $q->leftJoin('profit_centers as pc','pc.profit_center_code','=','P.profit_center_code')
              ->addSelect('pc.profit_center_name');
        }

        $rows = $q->addSelect([
                DB::raw('P.profit_center_code AS profit_center_code'),
                DB::raw('COALESCE(A.assigned_m3,0) AS assigned_m3'),
                DB::raw('COALESCE(O.converted_m3,0) AS converted_m3'),
                DB::raw('COALESCE(O.prob_weighted_open_m3,0) AS prob_weighted_open_m3'),
                DB::raw('COALESCE(O.count_total,0) AS count_total'),
                DB::raw('COALESCE(O.count_open,0)  AS count_open'),
                DB::raw('COALESCE(O.count_won,0)   AS count_won'),
                DB::raw('COALESCE(O.count_lost,0)  AS count_lost'),
                DB::raw("COALESCE(pc.profit_center_name, A.pc_name_assign, O.pc_name_ops, P.profit_center_code) AS profit_center_name"),
            ])
            ->orderBy('P.profit_center_code')
            ->get();

        $items = [];
        $totAssigned = $totWon = $totOpenW = $totAvail = 0;
        $totCount = 0;

        foreach ($rows as $r) {
            $assigned = (float)$r->assigned_m3;
            $won      = (float)$r->converted_m3;
            $openW    = (float)$r->prob_weighted_open_m3;
            $avail    = max(0.0, $assigned - $won - $openW);

            $items[] = [
                'profit_center_code'        => (string)$r->profit_center_code,
                'profit_center_name'        => (string)$r->profit_center_name,
                'assigned_m3'               => $assigned,
                'converted_m3'              => $won,
                'prob_weighted_open_m3'     => $openW,
                'in_progress_m3'            => $openW,
                'available_m3'              => $avail,
                'count_total'               => (int)$r->count_total,
                'count_open'                => (int)$r->count_open,
                'count_won'                 => (int)$r->count_won,
                'count_lost'                => (int)$r->count_lost,
            ];

            $totAssigned += $assigned;
            $totWon      += $won;
            $totOpenW    += $openW;
            $totAvail    += $avail;
            $totCount    += (int)$r->count_total;
        }

        return response()->json([
            'items'  => $items,
            'totals' => [
                'assigned_m3'    => $totAssigned,
                'converted_m3'   => $totWon,
                'in_progress_m3' => $totOpenW,
                'available_m3'   => $totAvail,
                'count_total'    => $totCount,
            ]
        ]);
    }

    public function listByUser(Request $request, int $userId)
    {
        $fy     = $request->integer('fiscal_year') ?: null;
        $pcCode = $request->query('profit_center_code');

        $q = DB::table('extra_quota_assignments as a')->where('a.user_id', $userId);
        if ($fy) $q->where('a.fiscal_year', $fy);
        if ($pcCode !== null && $pcCode !== '') $q->where('a.profit_center_code', $pcCode);

        if (Schema::hasTable('profit_centers')) {
            $q->leftJoin('profit_centers as pc', 'pc.profit_center_code', '=', 'a.profit_center_code')
            ->addSelect(
                'a.id','a.user_id','a.fiscal_year','a.profit_center_code','a.volume','a.updated_at',
                DB::raw("COALESCE(pc.profit_center_name, a.profit_center_code) AS profit_center_name")
            );
        } else {
            $q->select('a.id','a.user_id','a.fiscal_year','a.profit_center_code','a.volume','a.updated_at',
                    DB::raw("a.profit_center_code AS profit_center_name"));
        }

        return response()->json($q->orderBy('a.fiscal_year')->orderBy('a.profit_center_code')->get());
    }

    public function listAllByUserFY(Request $request, int $userId)
    {
        $fy = (int) ($request->query('fiscal_year') ?? date('Y'));

        if (!Schema::hasTable('profit_centers')) {
            // fallback
            return $this->listByUser($request, $userId);
        }

        $rows = DB::table('profit_centers as pc')
            ->leftJoin('extra_quota_assignments as a', function($j) use ($userId, $fy){
                $j->on('a.profit_center_code','=','pc.profit_center_code')
                ->where('a.user_id','=',$userId)
                ->where('a.fiscal_year','=',$fy);
            })
            ->select([
                'a.id',
                DB::raw("$userId as user_id"),
                DB::raw("$fy as fiscal_year"),
                'pc.profit_center_code',
                'pc.profit_center_name',
                DB::raw('COALESCE(a.volume, 0) as volume'),
                'a.updated_at',
            ])
            ->orderBy('pc.profit_center_code')
            ->get();

        return response()->json($rows);
    }

    public function updateAssignmentVolume(Request $request, int $id)
    {
        $data = $request->validate([
            'volume' => 'required|integer|min:0',
        ]);

        $exists = DB::table('extra_quota_assignments')->where('id', $id)->exists();
        if (!$exists) abort(404, 'Assignment not found');

        DB::table('extra_quota_assignments')->where('id', $id)->update([
            'volume'     => (int)$data['volume'],
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id, 'volume' => (int)$data['volume']]);
    }

    public function upsertAssignment(Request $request)
    {
        $data = $request->validate([
            'user_id'            => 'required|integer|min:1',
            'fiscal_year'        => 'required|integer',
            'profit_center_code' => 'required',
            'volume'             => 'required|integer|min:0',
            'is_published'       => 'nullable|boolean',
        ]);

        $rec = ExtraQuotaAssignment::updateOrCreate(
            [
                'user_id'            => (int)$data['user_id'],
                'fiscal_year'        => (int)$data['fiscal_year'],
                'profit_center_code' => (string)$data['profit_center_code'],
            ],
            [
                'volume'       => (int)$data['volume'],
                'is_published' => array_key_exists('is_published',$data) ? (bool)$data['is_published'] : true,
                'updated_at'   => now(),
            ]
        );

        return response()->json([
            'id'                 => $rec->id,
            'user_id'            => (int)$rec->user_id,
            'fiscal_year'        => (int)$rec->fiscal_year,
            'profit_center_code' => (string)$rec->profit_center_code,
            'volume'             => (int)$rec->volume,
        ]);
    }
}