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

    // GET /api/me/clients  → [{ id, name }]
    public function getClients(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $cpcIds = Assignment::where('user_id', $user->id)->pluck('client_profit_center_id');
        if ($cpcIds->isEmpty()) return response()->json([]);

        $clientNumbers = ClientProfitCenter::whereIn('id', $cpcIds)
            ->pluck('client_group_number')->filter()->unique();
        if ($clientNumbers->isEmpty()) return response()->json([]);

        $clients = Client::whereIn('client_group_number', $clientNumbers)
            ->select(['client_group_number as id', DB::raw('client_name as name')])
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

    // GET /api/me/assignments → { clientToPc: { [clientId]: [pcId...] }, pcToClient: { [pcId]: [clientId...] } }
    public function getAssignments(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $assignments = Assignment::with(['clientProfitCenter:id,client_group_number,profit_center_code'])
            ->where('user_id', $user->id)
            ->get(['id','client_profit_center_id','team_id','user_id']);

        $clientToPc = [];
        $pcToClient = [];

        foreach ($assignments as $a) {
            $cpc = $a->clientProfitCenter;
            if (!$cpc) continue;

            $clientId = (int) $cpc->client_group_number;
            $pcId     = (int) $cpc->profit_center_code;

            $clientToPc[$clientId][] = $pcId;
            $pcToClient[$pcId][]     = $clientId;
        }

        $clientToPc = array_map(fn($arr) => array_values(array_unique($arr)), $clientToPc);
        $pcToClient = array_map(fn($arr) => array_values(array_unique($arr)), $pcToClient);

        return response()->json([
            'clientToPc' => $clientToPc,
            'pcToClient' => $pcToClient,
        ]);
    }

    // ========================= SERIES (WITH FORMULAS) =========================
    // GET /api/forecast/series?clientId=&profitCenterId=
public function getSeries(Request $request)
{
    $user = $request->user();
    if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

    $data = $request->validate([
        'clientId'       => 'required|integer',  // clients.client_group_number
        'profitCenterId' => 'required|integer',  // profit_centers.profit_center_code
    ]);
    $clientId = (int) $data['clientId'];
    $pcId     = (int) $data['profitCenterId'];

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
        $s = (float) (Sale::where('client_profit_center_id', $cpcId)
            ->where('fiscal_year', $y)->where('month', $m)->value('volume') ?? 0.0);

        $b = (float) (Budget::where('client_profit_center_id', $cpcId)
            ->where('fiscal_year', $y)->where('month', $m)->value('volume') ?? 0.0);

        $ver = Forecast::where('client_profit_center_id', $cpcId)
            ->where('fiscal_year', $y)->where('month', $m)
            // ->where('user_id', $user->id) // activa si querés versionado por usuario
            ->max('version');

        $f = $ver ? (float) (Forecast::where('client_profit_center_id', $cpcId)
            ->where('fiscal_year', $y)->where('month', $m)
            ->where('version', $ver)
            // ->where('user_id', $user->id)
            ->value('volume') ?? 0.0) : 0.0;

        $sales[] = $s;
        $budget[] = $b;
        $forecast[] = $f;
        $orders[] = 0.0; // placeholder
    }

    // Acumulados
    $sales_cum = []; $budget_cum = []; $forecast_cum = [];
    $accS = 0.0; $accB = 0.0; $accF = 0.0;
    for ($i=0; $i<count($months); $i++){
        $accS += $sales[$i]   ?? 0.0; $sales_cum[]    = $accS;
        $accB += $budget[$i]  ?? 0.0; $budget_cum[]   = $accB;
        $accF += $forecast[$i]?? 0.0; $forecast_cum[] = $accF;
    }

    // Totales FY y línea horizontal de budget anual
    $sales_fy    = array_sum($sales);
    $budget_fy   = array_sum($budget);
    $forecast_fy = array_sum($forecast);
    $budget_fy_line = array_fill(0, count($months), $budget_fy);

    return response()->json([
        'months'         => $months,

        // mensuales
        'sales'          => $sales,
        'budget'         => $budget,
        'forecast'       => $forecast,
        'orders'         => $orders,

        // acumulados (para graficar la evolución)
        'sales_cum'      => $sales_cum,
        'budget_cum'     => $budget_cum,
        'forecast_cum'   => $forecast_cum,

        // totales FY + línea horizontal de budget anual
        'sales_fy'       => $sales_fy,
        'budget_fy'      => $budget_fy,
        'forecast_fy'    => $forecast_fy,
        'budget_fy_line' => $budget_fy_line,
    ]);
}

// ForecastController.php

public function getCurrentMonthVersions(Request $request)
{
    $user = $request->user();
    if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

    // Params: clientId = clients.client_group_number, profitCenterId = profit_centers.profit_center_code
    $data = $request->validate([
        'clientId'       => 'required|integer',
        'profitCenterId' => 'required|integer',
        'onlyMine'       => 'sometimes|boolean', // optional: return only current user's versions
    ]);

    $clientId = (int) $data['clientId'];
    $pcId     = (int) $data['profitCenterId'];
    $onlyMine = (bool) ($data['onlyMine'] ?? false);

    // Resolve CPC
    $cpcId = \App\Models\ClientProfitCenter::where('client_group_number', $clientId)
        ->where('profit_center_code', $pcId)
        ->value('id');
    if (!$cpcId) return response()->json(['message' => 'Not found'], 404);

    // Authorization via assignments
    $authorized = \App\Models\Assignment::where('user_id', $user->id)
        ->where('client_profit_center_id', $cpcId)
        ->exists();
    if (!$authorized) return response()->json(['message' => 'Forbidden'], 403);

    // Current FY + current month (FY = Apr..Mar)
    $today = now();
    $fiscalYear = $today->month >= 4 ? $today->year : ($today->year - 1);
    $month      = (int) $today->month;

    // Query all versions for this CPC + FY + month
    $q = \App\Models\Forecast::query()
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

    // Simple series for charting (x = version, y = volume)
    $versions = $rows->pluck('version')->toArray();
    $volumes  = $rows->pluck('volume')->toArray();

    return response()->json([
        'fiscal_year' => $fiscalYear,
        'month'       => $month,           // 1..12 (calendar month)
        'cpc_id'      => $cpcId,
        'count'       => count($rows),
        'versions'    => $versions,        // e.g. [1,2,3,...]
        'volumes'     => $volumes,         // e.g. [120,140,135,...]
        'items'       => $rows,            // full detail per version
    ]);
}


    // PUT /api/forecast/series  body: { clientId, profitCenterId, months[12], forecast[12] }
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

    // Reglas de edición
    $today = now();
    $secondTuesday = $this->getNthWeekdayOfMonth($today, 2, 3);
    $saved = 0; $eps = 0.0001;

    foreach ($data['months'] as $idx => $ym) {
        [$yStr, $mStr] = explode('-', $ym);
        $y = (int) $yStr; $m = (int) $mStr;
        $slot = $idx + 1;
        $val  = (float) $data['forecast'][$idx];

        if ($slot === 1 || $slot === 2) continue;
        if ($slot === 3 && $today->gt($secondTuesday)) continue;

        // Versión actual por CPC+mes+año (si querés por usuario, descomentar where user_id)
        $curVer = Forecast::where('client_profit_center_id', $cpcId)
            ->where('fiscal_year', $y)->where('month', $m)
            //->where('user_id', $user->id)
            ->max('version') ?? 0;

        $existing = null;
        if ($curVer > 0) {
            $existing = Forecast::where('client_profit_center_id', $cpcId)
                ->where('fiscal_year', $y)->where('month', $m)
                ->where('version', $curVer)
                // ->where('user_id', $user->id)
                ->value('volume');
        }
        if ($existing !== null && abs((float)$existing - $val) < $eps) continue;

        Forecast::create([
            'client_profit_center_id' => $cpcId,
            'fiscal_year'             => $y,
            'month'                   => $m,
            'version'                 => $curVer + 1,
            'volume'                  => $val,
            'user_id'                 => $user->id, // guarda quién creó la versión
        ]);
        $saved++;
    }

    return response()->json(['saved' => $saved]);
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
        if ($base == 0.0) return 0.0; // avoid NaN/INF; return 0% when baseline is zero
        return ($diff / $base) * 100.0;
    }
}