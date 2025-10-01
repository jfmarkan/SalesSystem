<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Console\Commands\Concerns\ImportsErpSales;

class ManualImportErpSales extends Command
{
    use ImportsErpSales;

    protected $signature = 'erp:manual-import-sales {from : YYYY-MM-DD} {--dry-run} {--show=50}';
    protected $description = 'Manual: repara desde una fecha (muestra rechazos en consola)';

    public function handle(): int
    {
        $from = (string)$this->argument('from');
        $dry  = (bool)$this->option('dry-run');
        $show = (int)$this->option('show');

        $res = $this->runImport($from, $dry, 'manual', $show > 0, $show);

        $msg = sprintf(
            'OK manual | from=%s seen=%d upserts=%d missingMap=%d shown=%d dry=%s',
            $res['from'], (int)$res['seen'], (int)$res['upserts'], (int)$res['missing'],
            (int)$res['shown'], $dry ? 'yes' : 'no'
        );
        $this->info($msg);
        return self::SUCCESS;
    }
}