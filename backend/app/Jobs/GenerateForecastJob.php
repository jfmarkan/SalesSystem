<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class GenerateForecastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $fiscalYear;
    public int $userId;

    public function __construct(int $fiscalYear, int $userId)
    {
        $this->fiscalYear = $fiscalYear;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        // TODO: Implementar: para clientes C/D copiar budget a forecast
        DB::table('sync_logs')->updateOrInsert(
            ['module' => 'generate_forecast_'.$this->fiscalYear],
            ['last_synced_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
