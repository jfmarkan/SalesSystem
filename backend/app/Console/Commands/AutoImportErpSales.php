<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Console\Commands\Concerns\ImportsErpSales;

class AutoImportErpSales extends Command
{
    use ImportsErpSales;

    protected $signature = 'erp:auto-import-sales';
    protected $description = 'Auto: 1–4 desde 1° mes anterior, 5+ desde 1° mes actual';

    public function handle(): int
    {
        $today = now();
        $from = $today->day <= 4
            ? $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString()
            : $today->copy()->startOfMonth()->toDateString();

        $res = $this->runImport($from, false);
        $this->info("OK auto | from={$res['from']} seen={$res['seen']} upserts={$res['upserts']} missingCPC={$res['missing']}");
        return self::SUCCESS;
    }
}
