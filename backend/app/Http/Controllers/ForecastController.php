<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForecastController extends Controller
{
    // ... selectorOptions, forecastList, monthlyEvolution, versionHistory (igual que ya te pasé)

    public function detail(Request $request, int $assignmentId)
    {
        $today = now();
        $startMonth = (int)$today->format('n'); // mes actual
        $startYear  = (int)$today->format('Y');

        $clientPCID = (int) DB::table('tblAssignmentMatrix')->where('ID', $assignmentId)->value('ClientProfitCenterID');
        if (!$clientPCID) return response()->json(['error' => 'Ungültige Zuordnung.'], 422);

        $secondTuesday = $this->getNthWeekdayOfMonth($today, 2, 2); // 2=Tue, nth=3 (como en tu código)
        // nota: tu VBA dice "GetSecondTuesday" pero devuelve el 3er martes; replico ese comportamiento
        // nth=3:
        $secondTuesday = $this->getNthWeekdayOfMonth($today, 2, 3);

        $rows = [];
        for ($i = 1; $i <= 12; $i++) {
            $offset = $i - 2; // 1=mes pasado, 2=actual, 3=próximo
            $m = $startMonth + $offset;
            $y = $startYear;
            if ($m <= 0) { $m += 12; $y -= 1; }
            elseif ($m > 12) { $m -= 12; $y += 1; }

            $maxVer = DB::table('tblForecasts')
                ->where('AssignmentID', $assignmentId)
                ->where('ForecastMonth', $m)
                ->where('ForecastYear', $y)
                ->max('Version');

            $forecastVal = 0.0;
            if (!is_null($maxVer)) {
                $forecastVal = (float) (DB::table('tblForecasts')
                    ->where('AssignmentID', $assignmentId)
                    ->where('ForecastMonth', $m)
                    ->where('ForecastYear', $y)
                    ->where('Version', $maxVer)
                    ->value('ForecastValue') ?? 0.0);
            }

            $actualVal = (float) (DB::table('tblSales')
                ->where('ClientProfitCenterID', $clientPCID)
                ->where('SalesMonth', $m)
                ->where('SalesYear', $y)
                ->value('Volume') ?? 0.0);

            $budgetVal = (float) (DB::table('tblBudgets')
                ->where('ClientProfitCenterID', $clientPCID)
                ->where('BudgetMonth', $m)
                ->where('BudgetYear', $y)
                ->value('Volume') ?? 0.0);

            // locks: 1,2 siempre bloqueado. 3 editable hasta 3er martes. resto editable
            $editable = true;
            if ($i === 1 || $i === 2) $editable = false;
            elseif ($i === 3) $editable = now()->lte($secondTuesday);

            // visual hint (ApplyForecastVisualBackColor)
            $bgHint = 'grey';
            if ($i >= 3 && $i <= 9) {
                if ($i === 3) {
                    $bgHint = now()->lte($secondTuesday) ? 'white' : 'grey';
                } elseif ($i === 9) {
                    $bgHint = now()->lte($secondTuesday) ? 'grey' : 'white';
                } else {
                    $bgHint = 'white';
                }
            }

            $rows[] = [
                'slot' => $i,
                'display' => strtoupper(date('M', mktime(0,0,0,$m,1,$y))) . ' ' . $y,
                'month' => $m,
                'year' => $y,
                'forecast' => $forecastVal,
                'actual' => $actualVal,
                'budget' => $budgetVal,
                'editable' => $editable,
                'bgHint' => $bgHint, // white|grey
                // validation color decision la hacemos en front
            ];
        }

        return response()->json($rows);
    }

    public function summary(Request $request, int $assignmentId)
    {
        $today = now();
        $fy = (int)$today->format('n') >= 4 ? (int)$today->format('Y') : ((int)$today->format('Y') - 1);
        $cutoff = now()->startOfMonth()->subDay(); // último día del mes anterior

        $clientPCID = (int) DB::table('tblAssignmentMatrix')->where('ID', $assignmentId)->value('ClientProfitCenterID');
        if (!$clientPCID) return response()->json(['error' => 'Ungültige Zuordnung.'], 422);

        $totalBudgetFY = (float) DB::table('tblBudgets')
            ->where('ClientProfitCenterID', $clientPCID)
            ->where(function ($q) use ($fy) {
                $q->where(function ($q) use ($fy) {
                    $q->where('BudgetYear', $fy)->whereBetween('BudgetMonth', [4, 12]);
                })->orWhere(function ($q) use ($fy) {
                    $q->where('BudgetYear', $fy + 1)->whereBetween('BudgetMonth', [1, 3]);
                });
            })->sum('Volume');

        $budgetToLastMonth = (float) DB::table('tblBudgets')
            ->where('ClientProfitCenterID', $clientPCID)
            ->whereRaw("( (BudgetYear = ?) AND BudgetMonth BETWEEN 4 AND 12 ) OR ( (BudgetYear = ?) AND BudgetMonth BETWEEN 1 AND 3 )", [$fy, $fy+1])
            ->whereRaw("DATEFROMPARTS(BudgetYear, BudgetMonth, 1) <= ?", [$cutoff->toDateString()])
            ->sum('Volume');

        $salesFY = (float) DB::table('tblSales')
            ->where('ClientProfitCenterID', $clientPCID)
            ->where(function ($q) use ($fy) {
                $q->where(function ($q) use ($fy) {
                    $q->where('SalesYear', $fy)->whereBetween('SalesMonth', [4, 12]);
                })->orWhere(function ($q) use ($fy) {
                    $q->where('SalesYear', $fy + 1)->whereBetween('SalesMonth', [1, 3]);
                });
            })->sum('Volume');

        $salesToLastMonth = (float) DB::table('tblSales')
            ->where('ClientProfitCenterID', $clientPCID)
            ->whereRaw("( (SalesYear = ?) AND SalesMonth BETWEEN 4 AND 12 ) OR ( (SalesYear = ?) AND SalesMonth BETWEEN 1 AND 3 )", [$fy, $fy+1])
            ->whereRaw("DATEFROMPARTS(SalesYear, SalesMonth, 1) <= ?", [$cutoff->toDateString()])
            ->sum('Volume');

        // forecast FY (última versión por mes)
        $forecastFY = (float) DB::table('tblForecasts as f')
            ->selectRaw('SUM(f.ForecastValue) as s')
            ->where('AssignmentID', $assignmentId)
            ->where(function ($q) use ($fy) {
                $q->where(function ($q) use ($fy) {
                    $q->where('ForecastYear', $fy)->whereBetween('ForecastMonth', [4, 12]);
                })->orWhere(function ($q) use ($fy) {
                    $q->where('ForecastYear', $fy + 1)->whereBetween('ForecastMonth', [1, 3]);
                });
            })
            ->whereRaw('f.Version = (SELECT MAX(Version) FROM tblForecasts fx WHERE fx.AssignmentID=f.AssignmentID AND fx.ForecastYear=f.ForecastYear AND fx.ForecastMonth=f.ForecastMonth)')
            ->value('s') ?? 0.0;

        $forecastToLastMonth = (float) DB::table('tblForecasts as f')
            ->selectRaw('SUM(f.ForecastValue) as s')
            ->where('AssignmentID', $assignmentId)
            ->where(function ($q) use ($fy) {
                $q->where(function ($q) use ($fy) {
                    $q->where('ForecastYear', $fy)->whereBetween('ForecastMonth', [4, 12]);
                })->orWhere(function ($q) use ($fy) {
                    $q->where('ForecastYear', $fy + 1)->whereBetween('ForecastMonth', [1, 3]);
                });
            })
            ->whereRaw("DATEFROMPARTS(ForecastYear, ForecastMonth, 1) <= ?", [$cutoff->toDateString()])
            ->whereRaw('f.Version = (SELECT MAX(Version) FROM tblForecasts fx WHERE fx.AssignmentID=f.AssignmentID AND fx.ForecastYear=f.ForecastYear AND fx.ForecastMonth=f.ForecastMonth)')
            ->value('s') ?? 0.0;

        // 6 meses futuros (horizonte dependiente del 3er miércoles como en tu función)
        $months = $this->getForecastHorizonMonths();
        $sixForecast = 0.0;
        $sixBudget = 0.0;
        foreach ($months as [$m,$y]) {
            $ver = DB::table('tblForecasts')
                ->where('AssignmentID', $assignmentId)
                ->where('ForecastMonth', $m)
                ->where('ForecastYear', $y)
                ->max('Version');
            if (!is_null($ver)) {
                $sixForecast += (float) (DB::table('tblForecasts')
                    ->where('AssignmentID', $assignmentId)
                    ->where('ForecastMonth', $m)
                    ->where('ForecastYear', $y)
                    ->where('Version', $ver)
                    ->value('ForecastValue') ?? 0.0);
            }
            $sixBudget += (float) (DB::table('tblBudgets')
                ->where('ClientProfitCenterID', $clientPCID)
                ->where('BudgetMonth', $m)
                ->where('BudgetYear', $y)
                ->value('Volume') ?? 0.0);
        }

        return response()->json([
            'totals' => [
                'forecastFY' => $forecastFY,
                'budgetFY' => $totalBudgetFY,
                'salesFY' => $salesFY,
                'forecastToLastMonth' => $forecastToLastMonth,
                'budgetToLastMonth' => $budgetToLastMonth,
                'salesToLastMonth' => $salesToLastMonth,
                'forecast6Future' => $sixForecast,
                'budget6Future' => $sixBudget,
            ],
        ]);
    }

    public function save(Request $request, int $assignmentId)
    {
        $userId = $request->user()->id;
        $today = now();
        $startMonth = (int)$today->format('n');
        $startYear  = (int)$today->format('Y');

        $payload = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.slot' => 'required|integer|min:1|max:12',
            'items.*.value' => 'required|numeric|min:0',
        ]);

        $secondTuesday = $this->getNthWeekdayOfMonth($today, 2, 3);

        $saved = 0;
        foreach ($payload['items'] as $it) {
            $i = (int)$it['slot'];
            $val = (float)$it['value'];

            // map slot -> (m,y)
            $offset = $i - 2;
            $m = $startMonth + $offset;
            $y = $startYear;
            if ($m <= 0) { $m += 12; $y -= 1; }
            elseif ($m > 12) { $m -= 12; $y += 1; }

            // lock rules
            if ($i === 1 || $i === 2) continue;
            if ($i === 3 && now()->gt($secondTuesday)) continue;

            $lastVer = DB::table('tblForecasts')
                ->where('AssignmentID', $assignmentId)
                ->where('ForecastYear', $y)
                ->where('ForecastMonth', $m)
                ->max('Version');

            $currentVersion = $lastVer ? (int)$lastVer : 0;
            $existingVal = null;
            if ($currentVersion > 0) {
                $existingVal = DB::table('tblForecasts')
                    ->where('AssignmentID', $assignmentId)
                    ->where('ForecastYear', $y)
                    ->where('ForecastMonth', $m)
                    ->where('Version', $currentVersion)
                    ->value('ForecastValue');
            }
            if ((float)($existingVal ?? 0.0) === $val) {
                continue; // no change
            }

            DB::table('tblForecasts')->insert([
                'AssignmentID' => $assignmentId,
                'ForecastYear' => $y,
                'ForecastMonth' => $m,
                'Version' => $currentVersion + 1,
                'ForecastValue' => $val,
                'CreatedAt' => now(),
                'CreatedBy' => $userId,
            ]);
            $saved++;
        }

        return response()->json(['saved' => $saved, 'message' => $saved > 0 ? 'Forecast gespeichert.' : 'Keine Änderungen.']);
    }

    private function getNthWeekdayOfMonth(\DateTimeInterface $ref, int $weekdayIso, int $nth): \DateTimeImmutable
    {
        // weekdayIso: 1=Mon..7=Sun, PHP date('N')
        $y = (int)$ref->format('Y');
        $m = (int)$ref->format('n');
        $first = new \DateTimeImmutable("$y-$m-01 00:00:00");
        $firstIso = (int)$first->format('N');
        $delta = ($weekdayIso - $firstIso + 7) % 7;
        $date = $first->modify("+$delta days")->modify('+' . ($nth - 1) . ' weeks');
        return $date;
    }

    private function getForecastHorizonMonths(): array
    {
        $today = now();
        // tu VBA usa "SecondWednesday" pero en realidad el 3er miércoles; replico
        $thirdWed = $this->getNthWeekdayOfMonth($today, 3, 3); // 3=Wed
        $base = $today->lte($thirdWed) ? $today : $today->addMonth();
        $baseMonth = (int)$base->format('n');
        $baseYear  = (int)$base->format('Y');

        $out = [];
        for ($i = 0; $i <= 5; $i++) {
            $m = (($baseMonth - 1 + $i) % 12) + 1;
            $y = $baseYear + intdiv(($baseMonth - 1 + $i), 12);
            $out[] = [$m, $y];
        }
        return $out;
    }
}
