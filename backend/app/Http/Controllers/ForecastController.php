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
use App\Models\Sales;
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
        try {
            $user = $request->user();
            if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

            $data = $request->validate([
                'clientId'       => 'required|integer', // clients.client_group_number
                'profitCenterId' => 'required|integer', // profit_centers.profit_center_code
            ]);
            $clientId = (int) $data['clientId'];
            $pcId     = (int) $data['profitCenterId'];

            // Resolve CPC id for (client, PC)
            $cpcId = ClientProfitCenter::where('client_group_number', $clientId)
                ->where('profit_center_code', $pcId)
                ->value('id');
            if (!$cpcId) return response()->json(['message'=>'Not found'], 404);

            // Ensure assignment belongs to user
            $assignmentId = Assignment::where('user_id', $user->id)
                ->where('client_profit_center_id', $cpcId)
                ->value('id');
            if (!$assignmentId) return response()->json(['message'=>'Forbidden'], 403);

            // Build FY months (Apr..Mar)
            $today = now();
            $fy    = $today->month >= 4 ? $today->year : ($today->year - 1);
            $pairs = $this->getFiscalMonths($fy); // [[m,y] x12]
            $months = array_map(fn($p) => sprintf('%04d-%02d', $p[1], $p[0]), $pairs);

            // Series
            $sales         = [];
            $budget        = [];
            $forecast      = [];
            $orders        = [];

            // Derived (per-month)
            $fcstVsBudget      = [];
            $fcstVsSales       = [];
            $budgetVsSales     = [];
            $fcstVsBudgetPct   = [];
            $fcstVsSalesPct    = [];
            $budgetVsSalesPct  = [];

            // Cumulative
            $salesCum    = [];
            $budgetCum   = [];
            $forecastCum = [];
            $accS = $accB = $accF = 0.0;

            foreach ($pairs as [$m,$y]) {
                // Base series
                $s = (float) (Sales::where('ClientProfitCenterID', $cpcId)
                    ->where('SalesYear', $y)->where('SalesMonth', $m)->value('Volume') ?? 0.0);
                $b = (float) (Budget::where('ClientProfitCenterID', $cpcId)
                    ->where('BudgetYear', $y)->where('BudgetMonth', $m)->value('Volume') ?? 0.0);
                $ver = Forecast::where('AssignmentID', $assignmentId)
                    ->where('ForecastYear', $y)->where('ForecastMonth', $m)->max('Version');
                $f = $ver ? (float) (Forecast::where('AssignmentID', $assignmentId)
                        ->where('ForecastYear', $y)->where('ForecastMonth', $m)
                        ->where('Version', $ver)->value('ForecastValue') ?? 0.0) : 0.0;

                $sales[]    = $s;
                $budget[]   = $b;
                $forecast[] = $f;
                $orders[]   = 0.0;

                // Variances (absolute)
                $vFB = $f - $b;
                $vFS = $f - $s;
                $vBS = $b - $s;
                $fcstVsBudget[]  = $vFB;
                $fcstVsSales[]   = $vFS;
                $budgetVsSales[] = $vBS;

                // Variances (percent vs baseline)
                $fcstVsBudgetPct[]  = $this->safePercent($vFB, $b); // vs Budget
                $fcstVsSalesPct[]   = $this->safePercent($vFS, $s); // vs Sales (Actual)
                $budgetVsSalesPct[] = $this->safePercent($vBS, $s); // vs Sales

                // Cumulative
                $accS += $s;  $salesCum[]    = $accS;
                $accB += $b;  $budgetCum[]   = $accB;
                $accF += $f;  $forecastCum[] = $accF;
            }

            // FY totals
            $fyTotals = [
                'sales'       => array_sum($sales),
                'budget'      => array_sum($budget),
                'forecast'    => array_sum($forecast),
                'fcstVsBudget' => array_sum($forecast) - array_sum($budget),
                'fcstVsSales'  => array_sum($forecast) - array_sum($sales),
                'budgetVsSales'=> array_sum($budget) - array_sum($sales),
            ];

            return response()->json([
                'months'            => $months,
                'sales'             => $sales,
                'budget'            => $budget,
                'forecast'          => $forecast,
                'orders'            => $orders,

                'fcstVsBudget'      => $fcstVsBudget,
                'fcstVsSales'       => $fcstVsSales,
                'budgetVsSales'     => $budgetVsSales,
                'fcstVsBudgetPct'   => $fcstVsBudgetPct,
                'fcstVsSalesPct'    => $fcstVsSalesPct,
                'budgetVsSalesPct'  => $budgetVsSalesPct,

                'salesCum'          => $salesCum,
                'budgetCum'         => $budgetCum,
                'forecastCum'       => $forecastCum,

                'fyTotals'          => $fyTotals,
            ]);
        } catch (\Throwable $e) {
            Log::error('getSeries failed', ['m'=>$e->getMessage(),'f'=>$e->getFile(),'l'=>$e->getLine()]);
            return response()->json(['message'=>'Server error in getSeries'], 500);
        }
    }

    // PUT /api/forecast/series  body: { clientId, profitCenterId, months[12], forecast[12] }
    public function saveSeries(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $data = $request->validate([
            'clientId'       => 'required|integer',
            'profitCenterId' => 'required|integer',
            'months'         => 'required|array|size:12',
            'months.*'       => 'required|string', // 'YYYY-MM'
            'forecast'       => 'required|array|size:12',
            'forecast.*'     => 'required|numeric|min:0',
        ]);

        $clientId = (int) $data['clientId'];
        $pcId     = (int) $data['profitCenterId'];

        $cpcId = ClientProfitCenter::where('client_group_number', $clientId)
            ->where('profit_center_code', $pcId)
            ->value('id');
        if (!$cpcId) return response()->json(['message'=>'Not found'], 404);

        $assignmentId = Assignment::where('user_id', $user->id)
            ->where('client_profit_center_id', $cpcId)
            ->value('id');
        if (!$assignmentId) return response()->json(['message'=>'Forbidden'], 403);

        // Edit rules: slots 1-2 locked; slot 3 allowed until second Tuesday
        $today = now();
        $secondTuesday = $this->getNthWeekdayOfMonth($today, 2, 3);
        $saved = 0; $epsilon = 0.0001;

        foreach ($data['months'] as $idx => $ym) {
            if (!preg_match('/^\d{4}\-\d{2}$/', $ym)) continue;
            [$yStr, $mStr] = explode('-', $ym);
            $y = (int) $yStr; $m = (int) $mStr;
            $slot = $idx + 1;
            $val  = (float) $data['forecast'][$idx];

            if ($slot === 1 || $slot === 2) continue;
            if ($slot === 3 && $today->gt($secondTuesday)) continue;

            $curVer = Forecast::where('AssignmentID', $assignmentId)
                ->where('ForecastYear', $y)->where('ForecastMonth', $m)
                ->max('Version') ?? 0;

            $existing = null;
            if ($curVer > 0) {
                $existing = Forecast::where('AssignmentID', $assignmentId)
                    ->where('ForecastYear', $y)->where('ForecastMonth', $m)
                    ->where('Version', $curVer)->value('ForecastValue');
            }

            if ($existing !== null && abs((float)$existing - $val) < $epsilon) continue;

            Forecast::create([
                'AssignmentID'  => $assignmentId,
                'ForecastYear'  => $y,
                'ForecastMonth' => $m,
                'Version'       => $curVer + 1,
                'ForecastValue' => $val,
                'CreatedAt'     => now(),
                'CreatedBy'     => $user->id,
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
