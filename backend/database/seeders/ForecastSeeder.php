<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

/**
 * ForecastSeeder
 * - Lee CSV: database/seeders/data/tblForecasts.csv
 * - Acepta headers snake_case y variantes (ForecastYear, ForecastMonth, etc.)
 * - Valida: CPC existe, mes 1..12, volume numérico. version<=0 -> 1.
 * - Dedupe por (client_profit_center_id, fiscal_year, month, version).
 * - user_id es opcional: si viene y NO existe el user, lo setea a null y lo reporta.
 * - Sin insertOrIgnore: inserta fila a fila y reporta L{n} con causa concreta.
 * - Resumen: inserted, skipped, duplicates, errors + listas de CPC y user_id problemáticos.
 */
class ForecastSeeder extends Seeder
{
    private const ABORT_ON_ERROR = false;

    public function run(): void
    {
        $file = database_path('seeders/data/tblForecasts.csv'); // ajusta si tu archivo se llama distinto
        if (!is_file($file)) {
            $this->command->error("CSV not found: $file");
            return;
        }

        [$headers, $rows] = $this->readCsv($file);
        $now = Carbon::now();

        $inserted = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = 0;

        $failedCpc = [];   // CPC que no existen
        $badUsers  = [];   // user_id que no existen

        // límites de INT firmado
        $MIN = -2147483648; $MAX = 2147483647;

        foreach ($rows as $i => $row) {
            $line = $i + 2; // 1=header

            $cpcId   = $this->ival($row,$headers,['client_profit_center_id','clientprofitcenterid','ClientProfitCenterID']);
            $year    = $this->ival($row,$headers,['fiscal_year','forecast_year','forecastyear','ForecastYear']);
            $month   = $this->ival($row,$headers,['month','forecast_month','forecastmonth','ForecastMonth']);
            $version = $this->ival($row,$headers,['version','Version']); if ($version <= 0) $version = 1;

            $valRaw  = $this->sval($row,$headers,['volume','forecast_value','ForecastValue','amount','Volume']);
            $volume  = is_null($valRaw) ? null : (int) round((float) str_replace(',','.', $valRaw));

            $userId  = $this->ival($row,$headers,['user_id','userid','UserID']); // opcional

            // Validaciones mínimas
            if (!$year || !$month || $month < 1 || $month > 12 || is_null($volume)) {
                $skipped++;
                $this->command->warn("L{$line} SKIP: datos inválidos (y={$year}, m={$month}, vol=".var_export($volume,true).")");
                continue;
            }

            // Validar CPC existente
            if (!$cpcId || !DB::table('client_profit_centers')->where('id',$cpcId)->exists()) {
                $skipped++;
                if ($cpcId > 0) $failedCpc[] = $cpcId;
                $this->command->warn("L{$line} SKIP: CPC inexistente (cpc_id={$cpcId})");
                continue;
            }

            // user_id opcional: si viene pero NO existe, lo anulamos y lo reportamos
            if ($userId > 0 && !DB::table('users')->where('id',$userId)->exists()) {
                $badUsers[] = $userId;
                $this->command->warn("L{$line} user_id inexistente ({$userId}) -> se usará NULL");
                $userId = null; // requiere que forecasts.user_id acepte null
            }

            // Cap a rango INT
            if ($volume < $MIN) $volume = $MIN;
            if ($volume > $MAX) $volume = $MAX;

            // Dedupe
            $exists = DB::table('forecasts')->where([
                'client_profit_center_id' => $cpcId,
                'fiscal_year'             => $year,
                'month'                   => $month,
                'version'                 => $version,
            ])->exists();
            if ($exists) {
                $duplicates++; $skipped++;
                $this->command->warn("L{$line} DUP: ya existe (cpc={$cpcId}, y={$year}, m={$month}, v={$version})");
                continue;
            }

            $payload = [
                'client_profit_center_id' => $cpcId,
                'fiscal_year'             => $year,
                'month'                   => $month,
                'version'                 => $version,
                'volume'                  => $volume,
                'user_id'                 => $userId ?: null,
                'created_at'              => $now,
                'updated_at'              => null,
                'deleted_at'              => null,
            ];

            try {
                DB::table('forecasts')->insert($payload);
                $inserted++;
            } catch (QueryException $e) {
                $errors++;
                $this->command->error("L{$line} ERROR: ".$e->getMessage());
                $this->command->line('Row='.json_encode($payload));
                if (self::ABORT_ON_ERROR) throw $e;
            }
        }

        // Resumen
        $this->command->info("Forecasts: inserted={$inserted}, skipped={$skipped}, duplicates={$duplicates}, errors={$errors}");

        // Listados agregados (únicos)
        $failedCpc = array_values(array_unique(array_filter($failedCpc, fn($v)=>$v>0)));
        $badUsers  = array_values(array_unique(array_filter($badUsers, fn($v)=>$v>0)));

        if (!empty($failedCpc)) $this->command->warn('client_profit_center_id no cargados (únicos): '.implode(',', $failedCpc));
        if (!empty($badUsers))  $this->command->warn('user_id inexistentes provistos en CSV (únicos): '.implode(',', $badUsers));
    }

    // --- helpers ---
    private function readCsv(string $path): array
    {
        $fh=fopen($path,'rb'); if(!$fh) return [[],[]];
        $first=fgets($fh); if($first===false){ fclose($fh); return [[],[]]; }
        $first=preg_replace('/^\xEF\xBB\xBF/','',$first);
        $semi=substr_count($first,';'); $coma=substr_count($first,',');
        $delim=($semi>$coma)?';':',';
        $headers=array_map(fn($h)=>$this->norm((string)$h), str_getcsv($first,$delim));
        $rows=[];
        while(($line=fgetcsv($fh,0,$delim))!==false){
            if(count($line)===1 && trim((string)$line[0])==='') continue;
            if(count($line)<count($headers)) $line=array_pad($line,count($headers),null);
            $rows[]=$line;
        }
        fclose($fh); return [$headers,$rows];
    }
    private function norm(string $s): string { return strtolower(str_replace([' ','-'],'_',trim($s))); }
    private function sval(array $row,array $headers,array $cands): ?string {
        foreach($cands as $c){ $k=$this->norm($c); $i=array_search($k,$headers,true);
            if($i!==false && isset($row[$i]) && $row[$i]!=='') return trim((string)$row[$i]); }
        return null;
    }
    private function ival(array $row,array $headers,array $cands): int {
        $v=$this->sval($row,$headers,$cands);
        if ($v===null) return 0;
        if (!preg_match('/-?\d+/', $v, $m)) return 0;
        return (int)$m[0];
    }
}