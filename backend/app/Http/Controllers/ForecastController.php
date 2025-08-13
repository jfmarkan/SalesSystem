<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\ProfitCenter;
use App\Models\Forecast;
use App\Models\Sales;
use App\Models\Budget;

class ForecastController extends Controller
{
    public function detail(Request $request, int $assignmentId)
    {
        $user = $request->user();
        $assignment = Assignment::with('team.members')->findOrFail($assignmentId);

        if (!$this->userIsAuthorized($assignment, $user)) {
            abort(403);
        }

        $clientPCID = $assignment->client_profit_center_id;
        if (!$clientPCID) {
            return response()->json(['error' => 'Ungültige Zuordnung.'], 422);
        }

        $today = now();
        $fy = $today->month >= 4 ? $today->year : $today->year - 1;
        $secondTuesday = $this->getNthWeekdayOfMonth($today, 2, 3);
        $months = $this->getFiscalMonths($fy);

        $rows = [];

        foreach ($months as $i => [$m, $y]) {
            $slot = $i + 1;

            $ver = Forecast::where('AssignmentID', $assignmentId)
                ->where('ForecastMonth', $m)
                ->where('ForecastYear', $y)
                ->max('Version');

            $forecastVal = $ver
                ? (float) Forecast::where('AssignmentID', $assignmentId)
                    ->where('ForecastMonth', $m)
                    ->where('ForecastYear', $y)
                    ->where('Version', $ver)
                    ->value('ForecastValue') ?? 0.0
                : 0.0;

            $actualVal = (float) Sales::where('ClientProfitCenterID', $clientPCID)
                ->where('SalesMonth', $m)
                ->where('SalesYear', $y)
                ->value('Volume') ?? 0.0;

            $budgetVal = (float) Budget::where('ClientProfitCenterID', $clientPCID)
                ->where('BudgetMonth', $m)
                ->where('BudgetYear', $y)
                ->value('Volume') ?? 0.0;

            $editable = true;
            if ($slot === 1 || $slot === 2) $editable = false;
            elseif ($slot === 3) $editable = $today->lte($secondTuesday);

            $bgHint = 'grey';
            if ($slot >= 3 && $slot <= 9) {
                if ($slot === 3) {
                    $bgHint = $today->lte($secondTuesday) ? 'white' : 'grey';
                } elseif ($slot === 9) {
                    $bgHint = $today->lte($secondTuesday) ? 'grey' : 'white';
                } else {
                    $bgHint = 'white';
                }
            }

            $rows[] = [
                'slot'     => $slot,
                'display'  => strtoupper(date('M', mktime(0, 0, 0, $m, 1, $y))) . ' ' . $y,
                'month'    => $m,
                'year'     => $y,
                'forecast' => $forecastVal,
                'actual'   => $actualVal,
                'budget'   => $budgetVal,
                'editable' => $editable,
                'bgHint'   => $bgHint,
            ];
        }

        return response()->json($rows);
    }

    public function save(Request $request, int $assignmentId)
    {
        $user = $request->user();
        $assignment = Assignment::with('team.members')->findOrFail($assignmentId);

        if (!$this->userIsAuthorized($assignment, $user)) {
            abort(403);
        }

        $today = now();
        $fy = $today->month >= 4 ? $today->year : $today->year - 1;
        $months = $this->getFiscalMonths($fy);

        $payload = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.slot'  => 'required|integer|min:1|max:12',
            'items.*.value' => 'required|numeric|min:0',
        ]);

        $secondTuesday = $this->getNthWeekdayOfMonth($today, 2, 3);
        $saved = 0;

        foreach ($payload['items'] as $item) {
            $slot = (int) $item['slot'];
            $val = (float) $item['value'];
            [$m, $y] = $months[$slot - 1];

            if ($slot === 1 || $slot === 2) continue;
            if ($slot === 3 && $today->gt($secondTuesday)) continue;

            $currentVer = Forecast::where('AssignmentID', $assignmentId)
                ->where('ForecastYear', $y)
                ->where('ForecastMonth', $m)
                ->max('Version') ?? 0;

            $existingVal = Forecast::where('AssignmentID', $assignmentId)
                ->where('ForecastYear', $y)
                ->where('ForecastMonth', $m)
                ->where('Version', $currentVer)
                ->value('ForecastValue');

            if (abs(($existingVal ?? 0.0) - $val) < 0.0001) continue;

            Forecast::create([
                'AssignmentID'   => $assignmentId,
                'ForecastYear'   => $y,
                'ForecastMonth'  => $m,
                'Version'        => $currentVer + 1,
                'ForecastValue'  => $val,
                'CreatedAt'      => now(),
                'CreatedBy'      => $user->id,
            ]);

            $saved++;
        }

        return response()->json([
            'saved'   => $saved,
            'message' => $saved > 0 ? 'Forecast gespeichert.' : 'Keine Änderungen.',
        ]);
    }

    public function summary(Request $request, int $assignmentId)
    {
        $user = $request->user();
        $assignment = Assignment::with('team.members')->findOrFail($assignmentId);

        if (!$this->userIsAuthorized($assignment, $user)) {
            abort(403);
        }

        $today = now();
        $fy = $today->month >= 4 ? $today->year : $today->year - 1;
        $cutoff = $today->copy()->startOfMonth()->subDay(); // último día del mes anterior
        $months = $this->getFiscalMonths($fy);

        $clientPCID = $assignment->client_profit_center_id;
        if (!$clientPCID) {
            return response()->json(['error' => 'Ungültige Zuordnung.'], 422);
        }

        // Ventas, presupuesto y forecast FY
        $salesFY = 0.0;
        $salesToLastMonth = 0.0;
        $budgetFY = 0.0;
        $budgetToLastMonth = 0.0;
        $forecastFY = 0.0;
        $forecastToLastMonth = 0.0;

        foreach ($months as [$m, $y]) {
            $monthDate = \Carbon\Carbon::createFromDate($y, $m, 1);

            // Forecast
            $ver = Forecast::where('AssignmentID', $assignmentId)
                ->where('ForecastMonth', $m)
                ->where('ForecastYear', $y)
                ->max('Version');

            $forecastVal = $ver
                ? (float) Forecast::where('AssignmentID', $assignmentId)
                    ->where('ForecastMonth', $m)
                    ->where('ForecastYear', $y)
                    ->where('Version', $ver)
                    ->value('ForecastValue') ?? 0.0
                : 0.0;

            $forecastFY += $forecastVal;
            if ($monthDate->lte($cutoff)) {
                $forecastToLastMonth += $forecastVal;
            }

            // Budget
            $budgetVal = (float) Budget::where('ClientProfitCenterID', $clientPCID)
                ->where('BudgetMonth', $m)
                ->where('BudgetYear', $y)
                ->value('Volume') ?? 0.0;

            $budgetFY += $budgetVal;
            if ($monthDate->lte($cutoff)) {
                $budgetToLastMonth += $budgetVal;
            }

            // Sales
            $salesVal = (float) Sales::where('ClientProfitCenterID', $clientPCID)
                ->where('SalesMonth', $m)
                ->where('SalesYear', $y)
                ->value('Volume') ?? 0.0;

            $salesFY += $salesVal;
            if ($monthDate->lte($cutoff)) {
                $salesToLastMonth += $salesVal;
            }
        }

        // Próximos 6 meses a partir del tercer miércoles
        $sixForecast = 0.0;
        $sixBudget = 0.0;

        foreach ($this->getForecastHorizonMonths() as [$m, $y]) {
            $ver = Forecast::where('AssignmentID', $assignmentId)
                ->where('ForecastMonth', $m)
                ->where('ForecastYear', $y)
                ->max('Version');

            if (!is_null($ver)) {
                $sixForecast += (float) Forecast::where('AssignmentID', $assignmentId)
                    ->where('ForecastMonth', $m)
                    ->where('ForecastYear', $y)
                    ->where('Version', $ver)
                    ->value('ForecastValue') ?? 0.0;
            }

            $sixBudget += (float) Budget::where('ClientProfitCenterID', $clientPCID)
                ->where('BudgetMonth', $m)
                ->where('BudgetYear', $y)
                ->value('Volume') ?? 0.0;
        }

        return response()->json([
            'totals' => [
                'forecastFY'          => $forecastFY,
                'budgetFY'            => $budgetFY,
                'salesFY'             => $salesFY,
                'forecastToLastMonth' => $forecastToLastMonth,
                'budgetToLastMonth'   => $budgetToLastMonth,
                'salesToLastMonth'    => $salesToLastMonth,
                'forecast6Future'     => $sixForecast,
                'budget6Future'       => $sixBudget,
            ],
        ]);
    }

    public function selectorOptions(Request $request)
    {
        // Profit Centers ordenados por nombre
        $profitCenters = ProfitCenter::select('id', 'profit_center_name as name')
            ->orderBy('profit_center_name')
            ->get();

        // Clientes ordenados por nombre
        $clients = Client::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'profit_centers' => $profitCenters,
            'clients' => $clients,
        ]);
    }

    public function getClients(Request $request)
    {
        $user = $request->user();

        $assignments = Assignment::where('user_id', $user->id)->get();

        $clientGroupNumbers = ClientProfitCenter::whereIn('id', $assignments->pluck('client_profit_center_id'))
            ->pluck('client_group_number')
            ->unique()
            ->toArray();

        $clients = Client::whereIn('client_group_number', $clientGroupNumbers)->get();

        return response()->json($clients);
    }

    public function getProfitCenters(Request $request)
    {
        return ProfitCenter::select('profit_center_code', 'profit_center_name')
            ->orderBy('profit_center_code')
            ->get();
    }

    public function getAssignments(Request $request)
    {
        $user = $request->user();

        $assignments = Assignment::with('clientProfitCenter')
            ->where('user_id', $user->id)
            ->get();

        $clientToPc = [];
        $pcToClient = [];

        foreach ($assignments as $assignment) {
            $cpc = $assignment->clientProfitCenter;
            if (!$cpc) continue;

            $clientId = $cpc->client_id;
            $pcId = $cpc->profit_center_id;

            $clientToPc[$clientId][] = $pcId;
            $pcToClient[$pcId][] = $clientId;
        }

        // Quitar duplicados por si acaso
        $clientToPc = array_map(fn($arr) => array_values(array_unique($arr)), $clientToPc);
        $pcToClient = array_map(fn($arr) => array_values(array_unique($arr)), $pcToClient);

        return response()->json([
            'clientToPc' => $clientToPc,
            'pcToClient' => $pcToClient,
        ]);
    }

    // ========== Helpers ==========

    private function userIsAuthorized($assignment, $user): bool
    {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'manager', 'superadmin'])) {
            return true;
        }

        return $assignment->team
            && $assignment->team->members
            && $assignment->team->members->pluck('user_id')->contains($user->id);
    }

    private function getFiscalMonths(int $fy): array
    {
        $months = [];

        foreach (range(4, 12) as $m) {
            $months[] = [$m, $fy];
        }

        foreach (range(1, 3) as $m) {
            $months[] = [$m, $fy + 1];
        }

        return $months;
    }

    private function getNthWeekdayOfMonth(\DateTimeInterface $ref, int $weekdayIso, int $nth): \DateTimeImmutable
    {
        $y = (int)$ref->format('Y');
        $m = (int)$ref->format('n');
        $first = new \DateTimeImmutable("$y-$m-01 00:00:00");
        $firstIso = (int)$first->format('N');
        $delta = ($weekdayIso - $firstIso + 7) % 7;
        return $first->modify("+$delta days")->modify('+' . ($nth - 1) . ' weeks');
    }
}