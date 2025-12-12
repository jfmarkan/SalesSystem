<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\ProfitCenter;
use App\Models\ClientProfitCenter;
use App\Models\Forecast;
use App\Models\Sale;
use App\Models\Budget;

class ForecastController extends Controller
{
    // ========================= ME ENDPOINTS =========================
    public function getClients(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $cpcIds = Assignment::where('user_id', $user->id)->pluck('client_profit_center_id');
        if ($cpcIds->isEmpty()) {
            return response()->json([]);
        }

        $clientNumbers = ClientProfitCenter::whereIn('id', $cpcIds)
            ->pluck('client_group_number')
            ->filter()
            ->unique();

        if ($clientNumbers->isEmpty()) {
            return response()->json([]);
        }

        // 🔴 FILTRO: excluir clientes con classification_id = 5
        $clients = Client::whereIn('client_group_number', $clientNumbers)
            ->where('classification_id', '!=', 5)
            ->select([
                'client_group_number as id',
                DB::raw('client_name as name'),
            ])
            ->orderBy('client_name')
            ->get();

        return response()->json($clients);
    }


    // GET /api/me/profit-centers  → [{ id, code, name }]
    public function getProfitCenters(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $cpcIds = Assignment::where('user_id', $user->id)->pluck('client_profit_center_id');
        if ($cpcIds->isEmpty()) return response()->json([]);

        $pcCodes = ClientProfitCenter::whereIn('id', $cpcIds)
            ->pluck('profit_center_code')->filter()->unique();
        if ($pcCodes->isEmpty()) return response()->json([]);

        $pcs = ProfitCenter::whereIn('profit_center_code', $pcCodes)
            ->select([
                'profit_center_code as id',
                'profit_center_code as code',
                DB::raw('profit_center_name as name'),
            ])
            ->orderBy('profit_center_code')
            ->get();

        return response()->json($pcs);
    }

    // GET /api/me/assignments → { clientToPc: {...}, pcToClient: {...}, cpcIds: [...] }
    public function getAssignments(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $assignments = Assignment::with(['clientProfitCenter:id,client_group_number,profit_center_code'])
            ->where('user_id', $user->id)
            ->get(['id','client_profit_center_id','team_id','user_id']);

        $clientToPc = [];
        $pcToClient = [];
        $cpcIds = [];

        foreach ($assignments as $a) {
            $cpc = $a->clientProfitCenter;
            if (!$cpc) continue;

            $clientId = (int) $cpc->client_group_number;
            $pcId     = (int) $cpc->profit_center_code;
            $cpcId    = (int) $a->client_profit_center_id;

            $clientToPc[$clientId][] = $pcId;
            $pcToClient[$pcId][]     = $clientId;

            $cpcIds[] = [
                'client_id'              => $clientId,
                'profit_center_id'       => $pcId,
                'client_profit_center_id'=> $cpcId,
            ];
        }

        $clientToPc = array_map(fn($arr) => array_values(array_unique($arr)), $clientToPc);
        $pcToClient = array_map(fn($arr) => array_values(array_unique($arr)), $pcToClient);

        return response()->json([
            'clientToPc' => $clientToPc,
            'pcToClient' => $pcToClient,
            'cpcIds'     => $cpcIds,
        ]);
    }

    // ========================= SERIES (WITH FORMULAS) =========================
    // GET /api/forecast/series?clientId=&profitCenterId=&unit=
    public function getSeries(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $data = $request->validate([
            'clientId'       => 'required|integer',  // clients.client_group_number
            'profitCenterId' => 'required|integer',  // profit_centers.profit_center_code
            'unit'           => 'sometimes|string|in:m3,eur,vk_eh',
        ]);
        $clientId = (int) $data['clientId'];
        $pcId     = (int) $data['profitCenterId'];

        // ======== UNIDAD POR DEFECTO SEGÚN PC ========
        $requestedUnit = $data['unit'] ?? null;
        $isM3BasePc    = in_array($pcId, [110, 170, 171, 175], true);

        if ($requestedUnit === null) {
            // Sin unit en el query → default por PC
            $unit = $isM3BasePc ? Sale::UNIT_M3 : Sale::UNIT_UNITS;
        } else {
            // Respeta lo que venga del front (m3 / eur / vk_eh)
            $unit = strtolower($requestedUnit);
        }

        $salesColumn = Sale::columnForUnit($unit);
        // =============================================

        // CPC por (client_group_number + profit_center_code)
        $cpcId = ClientProfitCenter::where('client_group_number', $clientId)
            ->where('profit_center_code', $pcId)
            ->value('id');
        if (!$cpcId) return response()->json(['message' => 'Not found'], 404);

        // Autorización por assignments
        $authorized = Assignment::where('user_id', $user->id)
            ->where('client_profit_center_id', $cpcId)
            ->exists();
        if (!$authorized) return response()->json(['message' => 'Forbidden'], 403);

        // Meses FY (abr..mar)
        $today  = now();
        $fy     = $today->month >= 4 ? $today->year : ($today->year - 1);
        $pairs  = $this->getFiscalMonths($fy); // [[m,y] x12]
        $months = array_map(fn($p) => sprintf('%04d-%02d', $p[1], $p[0]), $pairs);

        // Series base
        $sales = []; $budget = []; $forecast = []; $orders = [];

        foreach ($pairs as [$m, $y]) {
            // SALES en la columna correcta según unidad, redondeado a entero
            $sRaw = (float) (Sale::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $y)->where('month', $m)
                ->value($salesColumn) ?? 0.0);
            $s = (int) round($sRaw);

            // BUDGET (volume), redondeado a entero
            $bRaw = (float) (Budget::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $y)->where('month', $m)
                ->value('volume') ?? 0.0);
            $b = (int) round($bRaw);

            // FORECAST, última versión, redondeado a entero
            $ver = Forecast::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $y)->where('month', $m)
                ->max('version');

            $fRaw = $ver ? (float) (Forecast::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $y)->where('month', $m)
                ->where('version', $ver)
                ->value('volume') ?? 0.0) : 0.0;
            $f = (int) round($fRaw);

            $sales[]    = $s;
            $budget[]   = $b;
            $forecast[] = $f;
            $orders[]   = 0; // placeholder, entero
        }

        // Acumulados (también enteros)
        $sales_cum = []; $budget_cum = []; $forecast_cum = [];
        $accS = 0; $accB = 0; $accF = 0;
        for ($i=0; $i<count($months); $i++){
            $accS += $sales[$i]    ?? 0; $sales_cum[]    = $accS;
            $accB += $budget[$i]   ?? 0; $budget_cum[]   = $accB;
            $accF += $forecast[$i] ?? 0; $forecast_cum[] = $accF;
        }

        // Totales FY y línea horizontal de budget anual (enteros)
        $sales_fy    = (int) array_sum($sales);
        $budget_fy   = (int) array_sum($budget);
        $forecast_fy = (int) array_sum($forecast);
        $budget_fy_line = array_fill(0, count($months), $budget_fy);

        return response()->json([
            'months'         => $months,

            // mensuales (enteros)
            'sales'          => $sales,
            'budget'         => $budget,
            'forecast'       => $forecast,
            'orders'         => $orders,

            // acumulados (enteros)
            'sales_cum'      => $sales_cum,
            'budget_cum'     => $budget_cum,
            'forecast_cum'   => $forecast_cum,

            // totales FY (enteros)
            'sales_fy'       => $sales_fy,
            'budget_fy'      => $budget_fy,
            'forecast_fy'    => $forecast_fy,
            'budget_fy_line' => $budget_fy_line,

            // metadatos de unidad
            'unit'           => $unit,
            'sales_column'   => $salesColumn,
        ]);
    }


    public function getCurrentMonthVersions(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $data = $request->validate([
            'clientId'       => 'required|integer',
            'profitCenterId' => 'required|integer',
            'onlyMine'       => 'sometimes|boolean',
        ]);

        $clientId = (int) $data['clientId'];
        $pcId     = (int) $data['profitCenterId'];
        $onlyMine = (bool) ($data['onlyMine'] ?? false);

        $cpcId = ClientProfitCenter::where('client_group_number', $clientId)
            ->where('profit_center_code', $pcId)
            ->value('id');
        if (!$cpcId) return response()->json(['message' => 'Not found'], 404);

        $authorized = Assignment::where('user_id', $user->id)
            ->where('client_profit_center_id', $cpcId)
            ->exists();
        if (!$authorized) return response()->json(['message' => 'Forbidden'], 403);

        $today      = now();
        $fiscalYear = $today->month >= 4 ? $today->year : ($today->year - 1);
        $month      = (int) $today->month;

        $q = Forecast::query()
            ->leftJoin('users', 'users.id', '=', 'forecasts.user_id')
            ->where('forecasts.client_profit_center_id', $cpcId)
            ->where('forecasts.fiscal_year', $fiscalYear)
            ->where('forecasts.month', $month);

        if ($onlyMine) {
            $q->where('forecasts.user_id', $user->id);
        }

        $rows = $q->orderBy('forecasts.version')
            ->get([
                'forecasts.id',
                'forecasts.version',
                'forecasts.volume',
                'forecasts.user_id',
                'forecasts.created_at',
                'forecasts.updated_at',
            ]);

        $versions = $rows->pluck('version')->toArray();
        $volumes  = $rows->pluck('volume')->toArray();

        return response()->json([
            'fiscal_year' => $fiscalYear,
            'month'       => $month,
            'cpc_id'      => $cpcId,
            'count'       => count($rows),
            'versions'    => $versions,
            'volumes'     => $volumes,
            'items'       => $rows,
        ]);
    }

    // PUT /api/forecast/series
    public function saveSeries(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $data = $request->validate([
            'clientId'       => 'required|integer',
            'profitCenterId' => 'required|integer',
            'months'         => ['required','array','size:12'],
            'months.*'       => ['required','regex:/^\d{4}\-\d{2}$/'],
            'forecast'       => ['required','array','size:12'],
            'forecast.*'     => 'required|numeric|min:0',
        ]);

        $clientId = (int) $data['clientId'];
        $pcId     = (int) $data['profitCenterId'];

        $cpcId = ClientProfitCenter::where('client_group_number', $clientId)
            ->where('profit_center_code', $pcId)
            ->value('id');
        if (!$cpcId) return response()->json(['message' => 'Not found'], 404);

        $authorized = Assignment::where('user_id', $user->id)
            ->where('client_profit_center_id', $cpcId)
            ->exists();
        if (!$authorized) return response()->json(['message' => 'Forbidden'], 403);

        $today         = now();
        $secondTuesday = $this->getNthWeekdayOfMonth($today, 2, 3);

        $eps      = 0.0001;
        $saved    = 0;
        $changes  = [];
        $unchanged= [];
        $locked   = [];

        foreach ($data['months'] as $idx => $ym) {
            [$yStr, $mStr] = explode('-', $ym);
            $y = (int) $yStr; $m = (int) $mStr;
            $slot = $idx + 1;
            $val  = (float) $data['forecast'][$idx];

            if ($slot === 1 || $slot === 2) {
                $locked[] = ['index'=>$idx,'month'=>$ym,'reason'=>'locked_past'];
                continue;
            }
            if ($slot === 3 && $today->gt($secondTuesday)) {
                $locked[] = ['index'=>$idx,'month'=>$ym,'reason'=>'locked_after_2nd_tuesday'];
                continue;
            }

            $curVer = Forecast::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $y)->where('month', $m)
                ->max('version') ?? 0;

            $existing = null;
            if ($curVer > 0) {
                $existing = Forecast::where('client_profit_center_id', $cpcId)
                    ->where('fiscal_year', $y)->where('month', $m)
                    ->where('version', $curVer)
                    ->value('volume');
            }

            if ($existing !== null && abs((float)$existing - $val) < $eps) {
                $unchanged[] = ['index'=>$idx,'month'=>$ym,'value'=>$val];
                continue;
            }

            Forecast::create([
                'client_profit_center_id' => $cpcId,
                'fiscal_year'             => $y,
                'month'                   => $m,
                'version'                 => $curVer + 1,
                'volume'                  => $val,
                'user_id'                 => $user->id,
            ]);
            $saved++;
            $changes[] = [
                'index'        => $idx,
                'month'        => $ym,
                'from'         => $existing,
                'to'           => $val,
                'version_from' => $curVer,
                'version_to'   => $curVer + 1,
            ];
        }

        $message = $saved > 0
            ? "Es wurden {$saved} Feld(er) geändert und gespeichert."
            : "Gespeichert. Keine Änderungen erkannt.";

        return response()->json([
            'ok'              => true,
            'changed_count'   => $saved,
            'changed_fields'  => $changes,
            'unchanged_count' => count($unchanged),
            'locked_count'    => count($locked),
            'unchanged'       => $unchanged,
            'locked'          => $locked,
            'message'         => $message,
        ]);
    }

    // ========================= Helpers =========================

    private function getFiscalMonths(int $fy): array
    {
        $out = [];
        foreach (range(4,12) as $m) $out[] = [$m, $fy];
        foreach (range(1,3)  as $m) $out[] = [$m, $fy + 1];
        return $out;
    }

    private function getNthWeekdayOfMonth(\DateTimeInterface $ref, int $weekdayIso, int $nth): \DateTimeImmutable
    {
        $y = (int) $ref->format('Y');
        $m = (int) $ref->format('n');
        $first = new \DateTimeImmutable("$y-$m-01 00:00:00");
        $firstIso = (int) $first->format('N');
        $delta = ($weekdayIso - $firstIso + 7) % 7;
        return $first->modify("+$delta days")->modify('+' . ($nth - 1) . ' weeks');
    }

    private function safePercent(float $diff, float $base): float
    {
        if ($base == 0.0) return 0.0;
        return ($diff / $base) * 100.0;
    }

    // GET /api/forecast/series-table?clientId=&profitCenterId=&unit=
    public function getSeriesTable(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $data = $request->validate([
            'clientId'       => 'required|integer',
            'profitCenterId' => 'required|integer',
            'unit'           => 'sometimes|string|in:m3,eur,vk_eh',
        ]);
        $clientId = (int) $data['clientId'];
        $pcId     = (int) $data['profitCenterId'];

        // ======== UNIDAD POR DEFECTO SEGÚN PC ========
        $requestedUnit = $data['unit'] ?? null;
        $isM3BasePc    = in_array($pcId, [110, 170, 171, 175], true);

        if ($requestedUnit === null) {
            $unit = $isM3BasePc ? Sale::UNIT_M3 : Sale::UNIT_UNITS;
        } else {
            $unit = strtolower($requestedUnit);
        }

        $salesColumn = Sale::columnForUnit($unit);
        // =============================================

        $cpcId = ClientProfitCenter::where('client_group_number', $clientId)
            ->where('profit_center_code', $pcId)
            ->value('id');
        if (!$cpcId) return response()->json(['message' => 'Not found'], 404);

        $authorized = Assignment::where('user_id', $user->id)
            ->where('client_profit_center_id', $cpcId)
            ->exists();
        if (!$authorized) return response()->json(['message' => 'Forbidden'], 403);

        $today = now();
        $fy    = $today->month >= 4 ? $today->year : ($today->year - 1);
        $isExtended = ($today->month >= 10 || $today->month <= 3);
        $count = $isExtended ? 18 : 12;

        $pairs = [];
        $y = $fy; $m = 4;
        for ($i=0; $i<$count; $i++) {
            $pairs[] = [$m, $y];
            $m++; if ($m > 12) { $m = 1; $y++; }
        }
        $months = array_map(fn($p) => sprintf('%04d-%02d', $p[1], $p[0]), $pairs);

        $sales = []; $budget = []; $forecast = []; $orders = [];

        foreach ($pairs as [$mm, $yy]) {
            $sRaw = (float) (Sale::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $yy)->where('month', $mm)
                ->value($salesColumn) ?? 0.0);
            $s = (int) round($sRaw);

            $bRaw = (float) (Budget::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $yy)->where('month', $mm)
                ->value('volume') ?? 0.0);
            $b = (int) round($bRaw);

            $ver = Forecast::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $yy)->where('month', $mm)
                ->max('version');

            $fRaw = $ver ? (float) (Forecast::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $yy)->where('month', $mm)
                ->where('version', $ver)
                ->value('volume') ?? 0.0) : 0.0;
            $f = (int) round($fRaw);

            $sales[]    = $s;
            $budget[]   = $b;
            $forecast[] = $f;
            $orders[]   = 0; // entero
        }

        return response()->json([
            'months'      => $months,
            'sales'       => $sales,
            'budget'      => $budget,
            'forecast'    => $forecast,
            'orders'      => $orders,
            'count'       => $count,
            'fiscal_year' => $fy,
            'extended'    => $isExtended,

            'unit'        => $unit,
            'sales_column'=> $salesColumn,
        ]);
    }


    // PUT /api/forecast/series-table
    public function saveSeriesTable(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $data = $request->validate([
            'clientId'       => 'required|integer',
            'profitCenterId' => 'required|integer',
            'months'         => ['required','array','min:12','max:18'],
            'months.*'       => ['required','regex:/^\d{4}\-\d{2}$/'],
            'forecast'       => ['required','array'],
            'forecast.*'     => 'required|numeric|min:0',
        ]);

        $months = $data['months'];
        if (count($months) !== count($data['forecast'])) {
            return response()->json(['message' => 'Length mismatch'], 422);
        }

        $clientId = (int) $data['clientId'];
        $pcId     = (int) $data['profitCenterId'];

        $cpcId = ClientProfitCenter::where('client_group_number', $clientId)
            ->where('profit_center_code', $pcId)
            ->value('id');
        if (!$cpcId) return response()->json(['message' => 'Not found'], 404);

        $authorized = Assignment::where('user_id', $user->id)
            ->where('client_profit_center_id', $cpcId)
            ->exists();
        if (!$authorized) return response()->json(['message' => 'Forbidden'], 403);

        $today         = now();
        $secondTuesday = $this->getNthWeekdayOfMonth($today, 2, 3);

        $eps      = 0.0001;
        $saved    = 0;
        $changes  = [];
        $unchanged= [];
        $locked   = [];

        foreach ($months as $idx => $ym) {
            [$yStr, $mStr] = explode('-', $ym);
            $y = (int) $yStr; $m = (int) $mStr;
            $slot = $idx + 1;
            $val  = (float) $data['forecast'][$idx];

            if ($slot === 1 || $slot === 2) {
                $locked[] = ['index'=>$idx,'month'=>$ym,'reason'=>'locked_past'];
                continue;
            }
            if ($slot === 3 && $today->gt($secondTuesday)) {
                $locked[] = ['index'=>$idx,'month'=>$ym,'reason'=>'locked_after_2nd_tuesday'];
                continue;
            }

            $curVer = Forecast::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $y)->where('month', $m)
                ->max('version') ?? 0;

            $existing = null;
            if ($curVer > 0) {
                $existing = Forecast::where('client_profit_center_id', $cpcId)
                    ->where('fiscal_year', $y)->where('month', $m)
                    ->where('version', $curVer)
                    ->value('volume');
            }

            if ($existing !== null && abs((float)$existing - $val) < $eps) {
                $unchanged[] = ['index'=>$idx,'month'=>$ym,'value'=>$val];
                continue;
            }

            Forecast::create([
                'client_profit_center_id' => $cpcId,
                'fiscal_year'             => $y,
                'month'                   => $m,
                'version'                 => $curVer + 1,
                'volume'                  => $val,
                'user_id'                 => $user->id,
            ]);
            $saved++;
            $changes[] = [
                'index'        => $idx,
                'month'        => $ym,
                'from'         => $existing,
                'to'           => $val,
                'version_from' => $curVer,
                'version_to'   => $curVer + 1,
            ];
        }

        $message = $saved > 0
            ? "Es wurden {$saved} Feld(er) geändert und gespeichert."
            : "Gespeichert. Keine Änderungen erkannt.";

        return response()->json([
            'ok'              => true,
            'changed_count'   => $saved,
            'changed_fields'  => $changes,
            'unchanged_count' => count($unchanged),
            'locked_count'    => count($locked),
            'unchanged'       => $unchanged,
            'locked'          => $locked,
            'message'         => $message,
        ]);
    }
}
