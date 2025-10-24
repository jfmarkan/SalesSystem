<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ForecastingService
{
    /**
     * Genera forecast desde budgets para un FY.
     * version: número de versión a escribir.
     * overwrite: true para sobrescribir si ya existe esa versión.
     */
    public function generateFromBudgets(int $fiscalYear, int $version = 1, bool $overwrite = false): array
    {
        if ($overwrite) {
            DB::table('forecasts')
              ->where('fiscal_year', $fiscalYear)
              ->where('version', $version)
              ->delete();
        }

        $rows = DB::table('budgets')
            ->select('client_profit_center_id', 'fiscal_year', 'month', 'volume')
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('client_profit_center_id')
            ->orderBy('month')
            ->get();

        $payload = [];
        $now = now();
        foreach ($rows as $r) {
            $payload[] = [
                'client_profit_center_id' => $r->client_profit_center_id,
                'fiscal_year'             => $r->fiscal_year,
                'month'                   => $r->month,
                'volume'                  => (int) $r->volume,
                'version'                 => $version,
                'user_id'                 => null,
                'created_at'              => $now,
                'updated_at'              => $now,
            ];
        }

        // upsert según unique (client_profit_center_id,fiscal_year,month,version)
        $chunks = array_chunk($payload, 1000);
        $inserted = 0;
        foreach ($chunks as $chunk) {
            DB::table('forecasts')->upsert(
                $chunk,
                ['client_profit_center_id','fiscal_year','month','version'],
                ['volume','updated_at']
            );
            $inserted += count($chunk);
        }

        return [
            'fiscal_year' => $fiscalYear,
            'version'     => $version,
            'rows'        => $inserted,
        ];
    }
}
