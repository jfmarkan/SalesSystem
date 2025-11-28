<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitCentersController extends Controller
{
    public function summary()
    {
        $total    = DB::table('profit_centers')->count();
        $active   = DB::table('profit_centers')->whereNull('deleted_at')->count();
        $archived = DB::table('profit_centers')->whereNotNull('deleted_at')->count();

        $clientCounts = DB::table('client_profit_centers')
            ->select('profit_center_code', DB::raw('COUNT(*) AS clients'))
            ->groupBy('profit_center_code')
            ->pluck('clients', 'profit_center_code');

        $seasonalityCoverage = DB::table('seasonalities')
            ->select('profit_center_code')
            ->distinct()
            ->count();

        $conversionCoverage = DB::table('unit_conversions')
            ->select('profit_center_code')
            ->distinct()
            ->count();

        $recent = DB::table('profit_centers')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return response()->json([
            'total'               => $total,
            'active'              => $active,
            'archived'            => $archived,
            'clients_per_pc'      => $clientCounts,
            'seasonality_covered' => $seasonalityCoverage,
            'conversion_covered'  => $conversionCoverage,
            'recent'              => $recent,
        ]);
    }

    public function index()
    {
        return response()->json(
            DB::table('profit_centers')
                ->orderBy('profit_center_code')
                ->get()
        );
    }

    public function link(Request $request)
    {
        $data = $request->validate([
            'client_group_number' => 'required|integer|exists:clients,client_group_number',
            'profit_center_code'  => 'required|integer|exists:profit_centers,profit_center_code',
        ]);

        $exists = DB::table('client_profit_centers')->where($data)->exists();
        if (!$exists) {
            DB::table('client_profit_centers')->insert($data + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rel = DB::table('client_profit_centers')->where($data)->first();
        return response()->json($rel, 201);
    }

    public function unlink(int $relationId)
    {
        DB::table('client_profit_centers')
            ->where('id', $relationId)
            ->update(['deleted_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'profit_center_code' => 'required|integer|unique:profit_centers,profit_center_code',
            'profit_center_name' => 'required|string',
        ]);

        $pc = \App\Models\ProfitCenter::create($data);
        return response()->json($pc, 201);
    }

    public function destroy(int $code)
    {
        $pc = \App\Models\ProfitCenter::findOrFail($code);
        $pc->delete();
        return response()->json(['ok' => true]);
    }

    /* ================== SEASONALITY ================== */

    /**
     * GET /settings/profit-centers/{code}/seasonality
     *
     * - Si se pasa ?fy=2025 → solo ese año.
     * - Si NO se pasa fy → devuelve TODAS las filas de ese PC (todos los fiscal_year).
     */
    public function getSeasonality(string $code, Request $request)
    {
        $query = \App\Models\Seasonality::where('profit_center_code', $code);

        $fy = $request->get('fy');

        if ($fy !== null && $fy !== '') {
            $fyInt = (int) $fy;
            if ($fyInt > 0) {
                $query->where('fiscal_year', $fyInt);
            }
        }

        $rows = $query
            ->orderBy('fiscal_year')
            ->get();

        return response()->json($rows);
    }

    /**
     * PUT /settings/profit-centers/{code}/seasonality
     *
     * Body esperado:
     * {
     *   "fiscal_year": 2025,
     *   "rows": [
     *     {
     *       "apr": 8.5, "may": 7.2, ..., "mar": 6.1
     *     }
     *   ]
     * }
     */
    public function updateSeasonality(string $code, Request $request)
    {
        $data = $request->validate([
            'fiscal_year' => 'required|integer',
            'rows'        => 'required|array|min:1',
        ]);

        foreach ($data['rows'] as $row) {
            \App\Models\Seasonality::updateOrCreate(
                [
                    'profit_center_code' => $code,
                    'fiscal_year'        => $data['fiscal_year'],
                ],
                array_merge(
                    $row,
                    [
                        'profit_center_code' => $code,
                        'fiscal_year'        => $data['fiscal_year'],
                    ]
                )
            );
        }

        return response()->json(['ok' => true]);
    }

    /* ================== CONVERSION ================== */

    /**
     * GET /settings/profit-centers/{code}/conversion
     *
     * - Si ?fy=2025 → solo ese año.
     * - Si no hay fy → todas las filas de unit_conversions para ese PC.
     */
    public function getConversion(string $code, Request $request)
    {
        $query = \App\Models\UnitConversion::where('profit_center_code', $code);

        $fy = $request->get('fy');

        if ($fy !== null && $fy !== '') {
            $fyInt = (int) $fy;
            if ($fyInt > 0) {
                $query->where('fiscal_year', $fyInt);
            }
        }

        $rows = $query
            ->orderBy('fiscal_year')
            ->get();

        return response()->json($rows);
    }

    /**
     * PUT /settings/profit-centers/{code}/conversion
     *
     * Body esperado:
     * {
     *   "fiscal_year": 2025,
     *   "rows": [
     *     {
     *       "factor_to_m3": 1.2345,
     *       "factor_to_euro": 0.9876,
     *       "unit": "m3"
     *     }
     *   ]
     * }
     */
    public function updateConversion(string $code, Request $request)
    {
        $data = $request->validate([
            'fiscal_year' => 'required|integer',
            'rows'        => 'required|array|min:1',
        ]);

        foreach ($data['rows'] as $row) {
            \App\Models\UnitConversion::updateOrCreate(
                [
                    'profit_center_code' => $code,
                    'fiscal_year'        => $data['fiscal_year'],
                ],
                array_merge(
                    $row,
                    [
                        'profit_center_code' => $code,
                        'fiscal_year'        => $data['fiscal_year'],
                    ]
                )
            );
        }

        return response()->json(['ok' => true]);
    }
}
