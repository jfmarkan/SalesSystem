<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBudgetController extends Controller
{
    private const FISCAL_START_MONTH = 4; // Apr

    public function generateAll(Request $request)
    {
        $validated = $request->validate([
            'fiscal_year'  => ['nullable','integer','min:1900'],
            'full_rebuild' => ['nullable','boolean'],
            'case'         => ['nullable','in:best,worst'],
            'default_pct'  => ['nullable','numeric','between:-100,100'],
        ]);

        $now        = Carbon::now();
        $targetFY   = $validated['fiscal_year'] ?? ($this->fiscalYearFromDate($now) + 1);
        $fullRebuild= (bool)($validated['full_rebuild'] ?? false);
        $caseType   = $validated['case'] ?? 'best';
        $defaultPct = $validated['default_pct'] ?? null;

        if ($fullRebuild) {
            // HARD DELETE (no soft) para liberar el índice único
            DB::table('budgets')->where('fiscal_year', $targetFY)->delete();
            DB::table('tblBudgetDebugLog')->where('FiscalYear', $targetFY)->delete();
        }

        // CPCs con ventas (fuente Access-like)
        $clientPCIds = DB::table('tblSales')
            ->select('ClientProfitCenterID')
            ->whereNotNull('ClientProfitCenterID')
            ->distinct()
            ->pluck('ClientProfitCenterID')
            ->filter(fn($v) => (int)$v > 0)
            ->values();

        $insertedTotal = 0;
        foreach ($clientPCIds as $cpcId) {
            $insertedTotal += $this->generateForClient((int)$cpcId, $targetFY, $caseType, $defaultPct);
        }

        return response()->json([
            'message' => 'Budgets wurden generiert.',
            'data' => [
                'target_fiscal_year' => $targetFY,
                'clients_processed'  => $clientPCIds->count(),
                'rows_inserted'      => $insertedTotal,
            ],
        ]);
    }

    public function generateOne(Request $request)
    {
        $validated = $request->validate([
            'client_profit_center_id' => ['required','integer','exists:client_profit_centers,id'],
            'fiscal_year'             => ['nullable','integer','min:1900'],
            'case'                    => ['nullable','in:best,worst'],
            'default_pct'             => ['nullable','numeric','between:-100,100'],
            'full_rebuild'            => ['nullable','boolean'],
        ]);

        $clientPCId  = (int)$validated['client_profit_center_id'];
        $now         = Carbon::now();
        $targetFY    = $validated['fiscal_year'] ?? ($this->fiscalYearFromDate($now) + 1);
        $caseType    = $validated['case'] ?? 'best';
        $defaultPct  = $validated['default_pct'] ?? null;
        $fullRebuild = (bool)($validated['full_rebuild'] ?? true);

        if ($fullRebuild) {
            DB::table('budgets')->where('client_profit_center_id', $clientPCId)->where('fiscal_year', $targetFY)->delete();
            DB::table('tblBudgetDebugLog')->where('ClientProfitCenterID', $clientPCId)->where('FiscalYear', $targetFY)->delete();
        }

        $inserted = $this->generateForClient($clientPCId, $targetFY, $caseType, $defaultPct);

        return response()->json([
            'message' => 'Budget wurde generiert.',
            'data' => [
                'client_profit_center_id' => $clientPCId,
                'target_fiscal_year'      => $targetFY,
                'rows_inserted'           => $inserted,
            ],
        ]);
    }

    // -------- core --------

    private function generateForClient(int $clientPCId, int $targetFY, string $caseType, ?float $defaultPct): int
    {

        $skip = DB::table('budget_cases')
            ->where('client_profit_center_id', $clientPCId)
            ->where('fiscal_year', $targetFY)
            ->value('skip_budget');

        if ($skip) return 0;

        $now      = Carbon::now();
        $capMonth = $now->month < 10 ? 9 : $now->month;
        $salesYear= $targetFY - 1; // Jan..cap from prev calendar year

        $totalSalesYTD = (float) DB::table('tblSales')
            ->where('ClientProfitCenterID', $clientPCId)
            ->where('SalesYear', $salesYear)
            ->whereBetween('SalesMonth', [1, $capMonth])
            ->sum('Volume');

        if ($totalSalesYTD <= 0) return 0;

        // Get ProfitCenterCode from modern or legacy table
        $pcCode = DB::table('client_profit_centers')->where('id', $clientPCId)->value('profit_center_code');
        if (!$pcCode) {
            $pcCode = DB::table('tblClientProfitCenterPivot')->where('ID', $clientPCId)->value('ProfitCenterCode');
        }
        if (!$pcCode) return 0;

        $seasonIdPrev = DB::table('tblProfitCenterSeasonality')
            ->where('FiscalYear', $targetFY - 1)
            ->where('ProfitCenterCode', $pcCode)
            ->value('SeasonalityID');

        $seasonIdTarget = DB::table('tblProfitCenterSeasonality')
            ->where('FiscalYear', $targetFY)
            ->where('ProfitCenterCode', $pcCode)
            ->value('SeasonalityID');

        if (!$seasonIdPrev || !$seasonIdTarget) return 0;

        $seasonPrev   = $this->loadSeasonRow((int)$seasonIdPrev);
        $seasonTarget = $this->loadSeasonRow((int)$seasonIdTarget);
        if (!$seasonPrev || !$seasonTarget) return 0;

        // YTD pct Jan..cap (Jan–Mar from prevFY seasonality, Apr..cap from targetFY)
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $ytdPct = 0.0;
        for ($i = 1; $i <= $capMonth; $i++) {
            $mName = $months[$i - 1];
            $src   = ($i <= 3) ? $seasonPrev : $seasonTarget;
            $ytdPct += (float)($src[$mName] ?? 0.0);
        }
        if ($ytdPct <= 0) return 0;

        $baseForecast = $totalSalesYTD / ($ytdPct / 100.0);

        // pct cascade: budget_cases -> tblForecastCases -> default -> 0
        $pct = $this->resolvePct($clientPCId, $targetFY, $caseType, $defaultPct);
        $totalBudget = $baseForecast * (1 + ($pct / 100.0));

        // Distribute to Apr..Mar (calendar months 4..12,1..3) BUT store fiscal_year=$targetFY and month=1..12 calendar
        $fiscalOrder = [
            ['name'=>'Apr','m'=>4],['name'=>'May','m'=>5],['name'=>'Jun','m'=>6],
            ['name'=>'Jul','m'=>7],['name'=>'Aug','m'=>8],['name'=>'Sep','m'=>9],
            ['name'=>'Oct','m'=>10],['name'=>'Nov','m'=>11],['name'=>'Dec','m'=>12],
            ['name'=>'Jan','m'=>1],['name'=>'Feb','m'=>2],['name'=>'Mar','m'=>3],
        ];

        $inserted = 0;

        DB::beginTransaction();
        try {
            foreach ($fiscalOrder as $slot) {
                $calMonth = $slot['m'];
                $calYear  = ($calMonth >= self::FISCAL_START_MONTH) ? $targetFY : ($targetFY + 1);
                $mName    = $slot['name'];
                $pctDist  = (float)($seasonTarget[$mName] ?? 0.0);
                if ($pctDist <= 0) continue;

                $monthlyVol = ($pctDist / 100.0) * $totalBudget;
                $monthlyVolInt = (int) max(0, round($monthlyVol, 0)); // budgets.volume is unsigned int

                // upsert into budgets (unique: cpc+fiscal_year+month)
                DB::table('budgets')->updateOrInsert(
                    [
                        'client_profit_center_id' => $clientPCId,
                        'fiscal_year'             => $targetFY,
                        'month'                   => $calMonth,
                    ],
                    [
                        'volume'                  => $monthlyVolInt,
                        'updated_at'              => now(),
                        'created_at'              => now(),
                    ]
                );

                // debug log (calendar year/month kept for traceability)
                DB::table('tblBudgetDebugLog')->insert([
                    'ClientProfitCenterID' => $clientPCId,
                    'FiscalYear'           => $targetFY,
                    'BudgetYear'           => $calYear,
                    'BudgetMonth'          => $calMonth,
                    'MonthName'            => $mName,
                    'SalesVolume'          => round($totalSalesYTD, 2),
                    'BestCase'             => $caseType === 'best'  ? $pct : null,
                    'WorstCase'            => $caseType === 'worst' ? $pct : null,
                    'SeasonalityBase'      => round($ytdPct, 2),
                    'ForecastBase'         => round($baseForecast, 2),
                    'TotalBudget'          => round($totalBudget, 2),
                    'MonthlyPct'           => round($pctDist, 2),
                    'MonthlyVolume'        => round($monthlyVol, 2),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);

                $inserted++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $inserted;
    }

    private function resolvePct(int $clientPCId, int $targetFY, string $caseType, ?float $defaultPct): float
    {
        // 1) budget_cases
        $fromBudgetCase = DB::table('budget_cases')
            ->where('client_profit_center_id', $clientPCId)
            ->where('fiscal_year', $targetFY)
            ->first();
        if ($fromBudgetCase) {
            $v = $caseType === 'best' ? $fromBudgetCase->best_case : $fromBudgetCase->worst_case;
            if ($v !== null) return (float)$v;
        }

        // 2) tblForecastCases
        $fromForecast = DB::table('tblForecastCases')
            ->where('ClientProfitCenterID', $clientPCId)
            ->where('FiscalYear', $targetFY)
            ->first();
        if ($fromForecast) {
            $col = $caseType === 'best' ? 'BestCase' : 'WorstCase';
            if (isset($fromForecast->{$col})) return (float)$fromForecast->{$col};
        }

        // 3) parameter
        if ($defaultPct !== null) return (float)$defaultPct;

        return 0.0;
    }

    private function fiscalYearFromDate(Carbon $date): int
    {
        $fy = $date->year;
        if ($date->month < self::FISCAL_START_MONTH) $fy -= 1;
        return $fy;
    }

    private function loadSeasonRow(int $seasonalityId): ?array
    {
        $row = DB::table('tblSeasonalities')->where('ID', $seasonalityId)->first();
        if (!$row) return null;
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $out = [];
        foreach ($months as $m) $out[$m] = isset($row->{$m}) ? (float)$row->{$m} : 0.0;
        return $out;
    }
}
