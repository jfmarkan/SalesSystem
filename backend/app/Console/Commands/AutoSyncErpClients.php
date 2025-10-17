<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Console\Commands\Concerns\ImportsErpClients;

class AutoSyncErpClients extends Command
{
    use ImportsErpClients;

    protected $signature = 'erp:auto-sync-clients {--dry-run} {--print} {--max=100}';
    protected $description = 'Sincroniza clientes/asignaciones desde ERP diariamente.';

    public function handle(): int
    {
        $dry   = (bool)$this->option('dry-run');
        $print = (bool)$this->option('print');
        $max   = (int)$this->option('max');

        $res = $this->runClientSync($dry, $print, $max);

        $this->info(sprintf(
            'OK clients | seen=%d clientUpserts=%d assignmentUpserts=%d skipped=%d',
            $res['seen'] ?? 0,
            $res['clientUpserts'] ?? 0,
            $res['assignmentUpserts'] ?? 0,
            $res['skipped'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
