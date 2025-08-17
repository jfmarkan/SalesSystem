<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Assignment;

class DetectDeviations extends Command
{
    protected $signature = 'deviations:detect {--user_id=}';
    protected $description = 'Detect deviations per user & profit center (Forecast vs Budget next 6 months, Sales vs Budget previous month)';

    public function handle(): int
    {
        $now = Carbon::now();

        if ($now->day < 4) {
            $this->info('Skipping: day < 4.');
            return self::SUCCESS;
        }

        $onlyUserId = $this->option('user_id') ? (int)$this->option('user_id') : null;

        // usuarios con assignments
        $userIds = Assignment::when($onlyUserId, fn($q) => $q->where('user_id', $onlyUserId))
            ->distinct()->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('No users with assignments.');
            return self::SUCCESS;
        }

        foreach ($userIds as $userId) {
            // PC codes del usuario
            $pcCodes = Assignment::query()
                ->join('client_profit_centers as cpc', 'cpc.id', '=', 'assignments.client_profit_center_id')
                ->where('assignments.user_id', $userId)
                ->distinct()
                ->pluck('cpc.profit_center_code');

            foreach ($pcCodes as $pcCode) {
                // IDs de CPC del user para este PC code
                $cpcIds = Assignment::query()
                    ->join('client_profit_centers as cpc', 'cpc.id', '=', 'assignments.client_profit_center_id')
                    ->where('assignments.user_id', $userId)
                    ->where('cpc.profit_center_code', $pcCode)
                    ->pluck('assignments.client_profit_center_id')
                    ->all();

                if (!$cpcIds) continue;

                // A) Forecast vs Budget (mes actual + 5)
                [$monthNow, $yearNow] = [$now->month, $now->year];
                $totalForecast = 0.0;
                $totalBudgetF  = 0.0;

                for ($i = 0; $i < 6; $i++) {
                    $m = $monthNow + $i;
                    $y = $yearNow;
                    if ($m > 12) { $m -= 12; $y += 1; }

                    $b = (float) DB::table('budgets')
                        ->whereIn('client_profit_center_id', $cpcIds)
                        ->where('fiscal_year', $y)->where('month', $m)
                        ->sum('volume');

                    // último forecast por CPC (sumados)
                    $fSum = 0.0;
                    foreach ($cpcIds as $cpcId) {
                        $ver = DB::table('forecasts')
                            ->where('client_profit_center_id', $cpcId)
                            ->where('fiscal_year', $y)->where('month', $m)
                            ->max('version');
                        if ($ver) {
                            $fv = (float) DB::table('forecasts')
                                ->where('client_profit_center_id', $cpcId)
                                ->where('fiscal_year', $y)->where('month', $m)
                                ->where('version', $ver)
                                ->value('volume');
                            $fSum += $fv;
                        }
                    }

                    $totalBudgetF  += $b;
                    $totalForecast += $fSum;
                }

                if ($totalBudgetF > 0.0) {
                    $ratioF = $totalForecast / $totalBudgetF; // agregado por PC code
                    if ($ratioF < 0.95 || $ratioF > 1.05) {
                        $this->upsertDeviation(
                            profitCenterCode: $pcCode,
                            userId:  $userId,
                            fy:      $yearNow,
                            m:       $monthNow,
                            type:    'FORECAST',
                            percent: round($ratioF * 100, 2),
                        );
                    }
                }

                // B) Sales vs Budget (mes anterior)
                $prev = $now->copy()->subMonth();
                $pm = (int)$prev->month;
                $py = (int)$prev->year;

                $sumSales  = (float) DB::table('sales')
                    ->whereIn('client_profit_center_id', $cpcIds)
                    ->where('fiscal_year', $py)->where('month', $pm)
                    ->sum('volume');

                $sumBudget = (float) DB::table('budgets')
                    ->whereIn('client_profit_center_id', $cpcIds)
                    ->where('fiscal_year', $py)->where('month', $pm)
                    ->sum('volume');

                if ($sumBudget > 0.0 && $sumSales > 0.0) {
                    $ratioS = $sumSales / $sumBudget;
                    if ($ratioS < 0.90 || $ratioS > 1.10) {
                        $this->upsertDeviation(
                            profitCenterCode: $pcCode,
                            userId:  $userId,
                            fy:      $py,
                            m:       $pm,
                            type:    'SALES',
                            percent: round($ratioS * 100, 2),
                        );
                    }
                }
            }
        }

        $this->info('Deviation detection finished.');
        return self::SUCCESS;
    }

    /**
     * Inserta/omite por clave única (profit_center_code + fy + month + type + user_id)
     */
    private function upsertDeviation(string $profitCenterCode, int $userId, int $fy, int $m, string $type, float $percent): void
    {
        $exists = DB::table('deviations')->where([
            'profit_center_code' => $profitCenterCode,
            'fiscal_year'        => $fy,
            'month'              => $m,
            'deviation_type'     => $type,
            'user_id'            => $userId,
        ])->exists();

        if ($exists) return;

        $now = Carbon::now();
        DB::table('deviations')->insert([
            'profit_center_code' => $profitCenterCode,
            'deviation_type'     => $type,
            'fiscal_year'        => $fy,
            'month'              => $m,
            'deviation_ratio'    => $percent, // ratio * 100
            'explanation'        => null,
            'user_id'            => $userId,
            'created_at'         => $now,
            'updated_at'         => $now,
            'deleted_at'         => null,
        ]);
    }
}
