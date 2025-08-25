<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * ExtraQuotaController
 *
 * Single-controller module that manages:
 * - extra_quota_available  (CEO total per fiscal year & PC)
 * - extra_quota_assignment (user allocations, publish lock)
 * - sales_opportunities    (versioned by opportunity_group_id + version)
 * - extra_quota_budget     (12 months per group/version)
 * - extra_quota_forecasts  (12 months per group/version, with created_by)
 *
 * Notes:
 * - Column names follow exactly the structure you provided (snake_case).
 * - Forecasts here are NOT per-month-versioned. They are keyed by (group, version, month, fiscal_year).
 * - Budgets store calculation_date (set to now()).
 * - Opportunities: for the first version, we set opportunity_group_id = the inserted row id (so you can group versions later).
 * - Listing of opportunities returns only the latest version per group (common UX).
 * - Fiscal ordering April→March is used in grid endpoints; storage uses calendar month numbers 1..12.
 */
class ExtraQuotaController extends Controller
{
    /** Fiscal start month: April = 4 (aligns with your Access logic) */
    private int $fiscal_start = 4;

    /** Build an array of 12 calendar months ordered by fiscal (e.g., [4,5,...,12,1,2,3]) */
    private function fiscalMonths(): array
    {
        $arr = [];
        for ($i = 0; $i < 12; $i++) {
            $arr[] = (($this->fiscal_start - 1 + $i) % 12) + 1;
        }
        return $arr;
    }

    /** ========== EXTRA QUOTA AVAILABLE ========== */

    // GET /api/extra-quota/available?fiscal_year=&profit_center_code=
    public function availableIndex(Request $req)
    {
        $rows = DB::table('extra_quota_available')
            ->when($req->filled('fiscal_year'), fn($q) => $q->where('fiscal_year', (int)$req->input('fiscal_year')))
            ->when($req->filled('profit_center_code'), fn($q) => $q->where('profit_center_code', $req->input('profit_center_code')))
            ->orderBy('fiscal_year')
            ->orderBy('profit_center_code')
            ->get();

        return response()->json($rows);
    }

    // POST /api/extra-quota/available
    // body: { fiscal_year, profit_center_code, volume }
    public function availableUpsert(Request $req)
    {
        $data = $req->validate([
            'fiscal_year' => ['required','integer'],
            'profit_center_code' => ['required','string','max:64'],
            'volume' => ['required','numeric','min:0'],
        ]);

        // Upsert by (fiscal_year, profit_center_code)
        DB::table('extra_quota_available')->updateOrInsert(
            [
                'fiscal_year' => $data['fiscal_year'],
                'profit_center_code' => $data['profit_center_code'],
            ],
            [
                'volume' => $data['volume'],
            ]
        );

        return response()->json(['ok' => true]);
    }

    // DELETE /api/extra-quota/available/{id}
    public function availableDelete(int $id)
    {
        DB::table('extra_quota_available')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }

    /** ========== EXTRA QUOTA ASSIGNMENTS ========== */

    // GET /api/extra-quota/assignments?fiscal_year=&profit_center_code=&user_id=
    public function assignmentsIndex(Request $req)
    {
        $rows = DB::table('extra_quota_assignment')
            ->when($req->filled('fiscal_year'), fn($q) => $q->where('fiscal_year', (int)$req->input('fiscal_year')))
            ->when($req->filled('profit_center_code'), fn($q) => $q->where('profit_center_code', $req->input('profit_center_code')))
            ->when($req->filled('user_id'), fn($q) => $q->where('user_id', (int)$req->input('user_id')))
            ->orderBy('fiscal_year')->orderBy('profit_center_code')->orderBy('user_id')
            ->get();

        return response()->json($rows);
    }

    // POST /api/extra-quota/assignments
    // body: { fiscal_year, profit_center_code, user_id, volume, is_published?, assignment_date? }
    public function assignmentsStore(Request $req)
    {
        $data = $req->validate([
            'fiscal_year' => ['required','integer'],
            'profit_center_code' => ['required','string','max:64'],
            'user_id' => ['required','integer','exists:users,id'],
            'volume' => ['required','numeric','min:0'],
            'is_published' => ['sometimes','boolean'],
            'assignment_date' => ['sometimes','date'],
        ]);

        $id = DB::table('extra_quota_assignment')->insertGetId([
            'fiscal_year' => $data['fiscal_year'],
            'profit_center_code' => $data['profit_center_code'],
            'user_id' => $data['user_id'],
            'volume' => $data['volume'],
            'is_published' => (bool)($data['is_published'] ?? false),
            'assignment_date' => $data['assignment_date'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id], 201);
    }

    // PATCH /api/extra-quota/assignments/{id}
    public function assignmentsUpdate(Request $req, int $id)
    {
        $row = DB::table('extra_quota_assignment')->where('id', $id)->first();
        abort_if(!$row, 404, 'Assignment not found');

        if ($row->is_published) {
            return response()->json(['error' => 'Assignment is published and cannot be modified'], 422);
        }

        $data = $req->validate([
            'fiscal_year' => ['sometimes','integer'],
            'profit_center_code' => ['sometimes','string','max:64'],
            'user_id' => ['sometimes','integer','exists:users,id'],
            'volume' => ['sometimes','numeric','min:0'],
            // is_published only via publish endpoint
            'assignment_date' => ['sometimes','nullable','date'],
        ]);

        $data['updated_at'] = now();
        DB::table('extra_quota_assignment')->where('id', $id)->update($data);

        return response()->json(['ok' => true]);
    }

    // POST /api/extra-quota/assignments/{id}/publish
    public function assignmentsPublish(int $id)
    {
        $row = DB::table('extra_quota_assignment')->where('id', $id)->first();
        abort_if(!$row, 404, 'Assignment not found');

        DB::table('extra_quota_assignment')->where('id', $id)->update([
            'is_published' => true,
            'assignment_date' => $row->assignment_date ?: now()->toDateString(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    // DELETE /api/extra-quota/assignments/{id}
    public function assignmentsDelete(int $id)
    {
        $row = DB::table('extra_quota_assignment')->where('id', $id)->first();
        abort_if(!$row, 404, 'Assignment not found');

        if ($row->is_published) {
            return response()->json(['error' => 'Assignment is published and cannot be deleted'], 422);
        }

        DB::table('extra_quota_assignment')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }

    /** ========== SALES OPPORTUNITIES (versioned) ========== */

    // GET /api/extra-quota/opportunities?user_id=&fiscal_year=&profit_center_code=&status=
    // Returns only LATEST version per opportunity_group_id
    public function opportunitiesIndex(Request $req)
    {
        // Subquery: latest version per group
        $latest = DB::table('sales_opportunities')
            ->select('opportunity_group_id', DB::raw('MAX(version) as version'))
            ->groupBy('opportunity_group_id');

        $q = DB::table('sales_opportunities as so')
            ->joinSub($latest, 'l', function ($join) {
                $join->on('so.opportunity_group_id', '=', 'l.opportunity_group_id')
                     ->on('so.version', '=', 'l.version');
            })
            ->when($req->filled('user_id'), fn($x) => $x->where('so.user_id', (int)$req->input('user_id')))
            ->when($req->filled('fiscal_year'), fn($x) => $x->where('so.fiscal_year', (int)$req->input('fiscal_year')))
            ->when($req->filled('profit_center_code'), fn($x) => $x->where('so.profit_center_code', $req->input('profit_center_code')))
            ->when($req->filled('status'), fn($x) => $x->where('so.status', $req->input('status')))
            ->orderByDesc('so.updated_at');

        return response()->json($q->paginate(20));
    }

    // GET /api/extra-quota/opportunities/{group_id}
    // Latest version + history light
    public function opportunityShowLatest(int $group_id)
    {
        $latest = DB::table('sales_opportunities')
            ->where('opportunity_group_id', $group_id)
            ->orderByDesc('version')
            ->first();

        abort_if(!$latest, 404, 'Opportunity group not found');

        $versions = DB::table('sales_opportunities')
            ->where('opportunity_group_id', $group_id)
            ->orderBy('version')
            ->get(['id','version','status','is_won','is_lost','created_at','updated_at']);

        return response()->json([
            'latest' => $latest,
            'versions' => $versions,
        ]);
    }

    // POST /api/extra-quota/opportunities
    // body: { user_id, fiscal_year, profit_center_code, opportunity_ammount, probability_pct,
    //         estimated_start_date?, comments?, status?, potential_client_name?, client_group_number? }
    public function opportunitiesStore(Request $req)
    {
        $data = $req->validate([
            'user_id' => ['required','integer','exists:users,id'],
            'fiscal_year' => ['required','integer'],
            'profit_center_code' => ['required','string','max:64'],
            'opportunity_ammount' => ['required','numeric','min:0'], // keep exact column name
            'probability_pct' => ['required','integer','min:0','max:100'],
            'estimated_start_date' => ['sometimes','nullable','date'],
            'comments' => ['sometimes','nullable','string'],
            'status' => ['sometimes','string','max:32'],
            'potential_client_name' => ['sometimes','nullable','string','max:255'],
            'client_group_number' => ['sometimes','nullable','string','max:64'],
        ]);

        return DB::transaction(function () use ($data) {
            // 1) Insert initial version with temporary group id (null)
            $id = DB::table('sales_opportunities')->insertGetId([
                'user_id' => $data['user_id'],
                'fiscal_year' => $data['fiscal_year'],
                'profit_center_code' => $data['profit_center_code'],
                'opportunity_ammount' => $data['opportunity_ammount'],
                'probability_pct' => $data['probability_pct'],
                'estimated_start_date' => $data['estimated_start_date'] ?? null,
                'comments' => $data['comments'] ?? null,
                'status' => $data['status'] ?? 'open',
                'version' => 1,
                'opportunity_group_id' => null, // set below to self-id
                'potential_client_name' => $data['potential_client_name'] ?? null,
                'is_won' => false,
                'won_at' => null,
                'is_lost' => false,
                'lost_at' => null,
                'client_group_number' => $data['client_group_number'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2) Set its group id to its own id (group anchor)
            DB::table('sales_opportunities')->where('id', $id)->update([
                'opportunity_group_id' => $id,
                'updated_at' => now(),
            ]);

            // 3) Return latest
            $row = DB::table('sales_opportunities')->where('id', $id)->first();
            return response()->json($row, 201);
        });
    }

    // POST /api/extra-quota/opportunities/{group_id}/version
    // body: all same fields as store + status + is_won/is_lost optional (auto-set dates)
    public function opportunityNewVersion(Request $req, int $group_id)
    {
        $current = DB::table('sales_opportunities')
            ->where('opportunity_group_id', $group_id)
            ->orderByDesc('version')
            ->first();

        abort_if(!$current, 404, 'Opportunity group not found');

        $data = $req->validate([
            'user_id' => ['required','integer','exists:users,id'],
            'fiscal_year' => ['required','integer'],
            'profit_center_code' => ['required','string','max:64'],
            'opportunity_ammount' => ['required','numeric','min:0'],
            'probability_pct' => ['required','integer','min:0','max:100'],
            'estimated_start_date' => ['sometimes','nullable','date'],
            'comments' => ['sometimes','nullable','string'],
            'status' => ['required','string','max:32'], // expected 'draft|open|won|lost' or similar
            'potential_client_name' => ['sometimes','nullable','string','max:255'],
            'client_group_number' => ['sometimes','nullable','string','max:64'],
            'is_won' => ['sometimes','boolean'],
            'is_lost' => ['sometimes','boolean'],
            'won_at' => ['sometimes','nullable','date'],
            'lost_at' => ['sometimes','nullable','date'],
        ]);

        $next_version = (int)$current->version + 1;

        $is_won = $data['is_won'] ?? ($data['status'] === 'won');
        $is_lost = $data['is_lost'] ?? ($data['status'] === 'lost');

        $won_at = $data['won_at'] ?? ($is_won ? now() : null);
        $lost_at = $data['lost_at'] ?? ($is_lost ? now() : null);

        $id = DB::table('sales_opportunities')->insertGetId([
            'user_id' => $data['user_id'],
            'fiscal_year' => $data['fiscal_year'],
            'profit_center_code' => $data['profit_center_code'],
            'opportunity_ammount' => $data['opportunity_ammount'],
            'probability_pct' => $data['probability_pct'],
            'estimated_start_date' => $data['estimated_start_date'] ?? null,
            'comments' => $data['comments'] ?? null,
            'status' => $data['status'],
            'version' => $next_version,
            'opportunity_group_id' => $group_id,
            'potential_client_name' => $data['potential_client_name'] ?? null,
            'is_won' => (bool)$is_won,
            'won_at' => $won_at,
            'is_lost' => (bool)$is_lost,
            'lost_at' => $lost_at,
            'client_group_number' => $data['client_group_number'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('sales_opportunities')->where('id', $id)->first();
        return response()->json($row);
    }

    /** ========== BUDGET ========== */

    // GET /api/extra-quota/budget/{group_id}/{version}?fiscal_year=2025
    // Returns 12 rows (April→March) with { month, fiscal_year, volume }
    public function budgetGrid(Request $req, int $group_id, int $version)
    {
        $fy = (int)($req->input('fiscal_year') ?? date('Y'));

        $raw = DB::table('extra_quota_budget')
            ->where('opportunity_group_id', $group_id)
            ->where('version', $version)
            ->get()
            ->keyBy(fn($r) => $r->fiscal_year.'-'.$r->month);

        $rows = [];
        foreach ($this->fiscalMonths() as $m) {
            $year = ($m >= $this->fiscal_start) ? $fy : ($fy + 1);
            $key = $year.'-'.$m;
            $rows[] = [
                'month' => $m,
                'fiscal_year' => $year,
                'volume' => isset($raw[$key]) ? (float)$raw[$key]->volume : 0.0,
            ];
        }

        return response()->json($rows);
    }

    // POST /api/extra-quota/budget/{group_id}/{version}/save
    // body: { items: [ { month, fiscal_year, volume }, ... ] }
    public function budgetSave(Request $req, int $group_id, int $version)
    {
        $payload = $req->validate([
            'items' => ['required','array','min:1'],
            'items.*.month' => ['required','integer','min:1','max:12'],
            'items.*.fiscal_year' => ['required','integer'],
            'items.*.volume' => ['required','numeric','min:0'],
        ]);

        foreach ($payload['items'] as $row) {
            DB::table('extra_quota_budget')->updateOrInsert(
                [
                    'opportunity_group_id' => $group_id,
                    'version' => $version,
                    'month' => (int)$row['month'],
                    'fiscal_year' => (int)$row['fiscal_year'],
                ],
                [
                    'volume' => (float)$row['volume'],
                    'calculation_date' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return response()->json(['ok' => true]);
    }

    /** ========== FORECASTS ========== */

    // GET /api/extra-quota/forecast/{group_id}/{version}?fiscal_year=2025
    // Returns 12 rows (April→March) with { month, fiscal_year, volume }
    public function forecastGrid(Request $req, int $group_id, int $version)
    {
        $fy = (int)($req->input('fiscal_year') ?? date('Y'));

        $raw = DB::table('extra_quota_forecasts')
            ->where('opportunity_group_id', $group_id)
            ->where('version', $version)
            ->get()
            ->keyBy(fn($r) => $r->fiscal_year.'-'.$r->month);

        $rows = [];
        foreach ($this->fiscalMonths() as $m) {
            $year = ($m >= $this->fiscal_start) ? $fy : ($fy + 1);
            $key = $year.'-'.$m;
            $rows[] = [
                'month' => $m,
                'fiscal_year' => $year,
                'volume' => isset($raw[$key]) ? (float)$raw[$key]->volume : 0.0,
            ];
        }

        return response()->json($rows);
    }

    // POST /api/extra-quota/forecast/{group_id}/{version}/save
    // body: { items: [ { month, fiscal_year, volume }, ... ] }
    public function forecastSave(Request $req, int $group_id, int $version)
    {
        $payload = $req->validate([
            'items' => ['required','array','min:1'],
            'items.*.month' => ['required','integer','min:1','max:12'],
            'items.*.fiscal_year' => ['required','integer'],
            'items.*.volume' => ['required','numeric','min:0'],
        ]);

        $user_id = $req->user()->id;

        foreach ($payload['items'] as $row) {
            DB::table('extra_quota_forecasts')->updateOrInsert(
                [
                    'opportunity_group_id' => $group_id,
                    'version' => $version,
                    'month' => (int)$row['month'],
                    'fiscal_year' => (int)$row['fiscal_year'],
                ],
                [
                    'volume' => (float)$row['volume'],
                    'created_by' => $user_id,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return response()->json(['ok' => true]);
    }
}
