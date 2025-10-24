<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BudgetingService;

class GenerateBudgets extends Command
{
    protected $signature = 'budgets:generate {fy : Fiscal year target} {--full} {--cutoff= : Calendar month 1..12}';
    protected $description = 'Generate budgets for a fiscal year';

    public function handle(BudgetingService $svc)
    {
        $fy = (int) $this->argument('fy');
        $full = (bool) $this->option('full');
        $cutoff = $this->option('cutoff') ? (int)$this->option('cutoff') : null;

        $res = $svc->generate($fy, $full, $cutoff);
        $this->info(json_encode($res));
        return 0;
    }
}
