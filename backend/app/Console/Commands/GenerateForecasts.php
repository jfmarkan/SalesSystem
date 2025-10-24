<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ForecastingService;

class GenerateForecasts extends Command
{
    protected $signature = 'forecasts:generate {fy : Fiscal year} {--version=1} {--overwrite}';
    protected $description = 'Generate forecasts from budgets';

    public function handle(ForecastingService $svc)
    {
        $fy = (int) $this->argument('fy');
        $ver = (int) $this->option('version');
        $overwrite = (bool) $this->option('overwrite');

        $res = $svc->generateFromBudgets($fy, $ver, $overwrite);
        $this->info(json_encode($res));
        return 0;
    }
}
