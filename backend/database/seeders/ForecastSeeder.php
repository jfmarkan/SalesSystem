<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ForecastSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('seeders/data/tblForecast.csv'); // ajustá si tu archivo se llama distinto (p.ej. tblForecasts.csv)
        if (!is_file($file)) {
            $this->command->error("CSV not found: $file");
            return;
        }

        [$headers, $rows] = $this->readCsv($file);
        $now = Carbon::now();
        $batch = [];
        $inserted = 0; $skipped = 0;

        foreach ($rows as $row) {
            $cpcId   = $this->ival($row,$headers,['client_profit_center_id','clientprofitcenterid','ClientProfitCenterID']);
            $year    = $this->ival($row,$headers,['fiscal_year','forecast_year','forecastyear','ForecastYear']);
            $month   = $this->ival($row,$headers,['month','forecast_month','forecastmonth','ForecastMonth']);
            $version = $this->ival($row,$headers,['version','Version']); if ($version<=0) $version = 1;

            $valRaw  = $this->sval($row,$headers,['volume','forecast_value','ForecastValue','amount','Volume']);
            $volume  = is_null($valRaw) ? null : (int) round((float) str_replace(',','.', $valRaw));

            // user_id opcional
            $userId  = $this->ival($row,$headers,['user_id','userid','UserID']); // si tu columna no permite null, dale un default acá

            if (!$year || !$month || is_null($volume)) { $skipped++; continue; }

            if (!$cpcId || !DB::table('client_profit_centers')->where('id',$cpcId)->exists()) {
                $this->command->warn("SKIP: CPC inexistente (cpc_id={$cpcId}, y={$year}, m={$month}, v={$version})");
                $skipped++; continue;
            }

            // Dedupe por (cpc, year, month, version)
            $exists = DB::table('forecasts')->where([
                'client_profit_center_id' => $cpcId,
                'fiscal_year'             => $year,
                'month'                   => $month,
                'version'                 => $version,
            ])->exists();
            if ($exists) { $skipped++; continue; }

            $batch[] = [
                'client_profit_center_id' => $cpcId,
                'fiscal_year'             => $year,
                'month'                   => $month,
                'version'                 => $version,
                'volume'                  => $volume,
                'user_id'                 => $userId ?: null, // si tu columna NO acepta null, reemplazá por un ID válido
                'created_at'              => $now,
                'updated_at'              => null,
                'deleted_at'              => null,
            ];

            if (count($batch) >= 200) {
                DB::table('forecasts')->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table('forecasts')->insert($batch);
            $inserted += count($batch);
        }

        $this->command->info("Forecasts: inserted=$inserted, skipped=$skipped");
    }

    // --- helpers ---
    private function readCsv(string $path): array
    {
        $fh=fopen($path,'rb'); if(!$fh) return [[],[]];
        $first=fgets($fh); if($first===false) return [[],[]];
        $first=preg_replace('/^\xEF\xBB\xBF/','',$first);
        $semi=substr_count($first,';'); $coma=substr_count($first,',');
        $delim=($semi>$coma)?';':',';
        $headers=array_map(fn($h)=>$this->norm($h), str_getcsv($first,$delim));
        $rows=[];
        while(($line=fgetcsv($fh,0,$delim))!==false){
            if(count($line)===1 && trim($line[0])==='') continue;
            if(count($line)<count($headers)) $line=array_pad($line,count($headers),null);
            $rows[]=$line;
        }
        fclose($fh); return [$headers,$rows];
    }
    private function norm(string $s): string { return strtolower(str_replace([' ','-'],'_',trim($s))); }
    private function sval(array $row,array $headers,array $cands): ?string {
        foreach($cands as $c){ $k=$this->norm($c); $i=array_search($k,$headers,true);
            if($i!==false && isset($row[$i]) && $row[$i]!=='') return trim($row[$i]); }
        return null;
    }
    private function ival(array $row,array $headers,array $cands): int {
        $v=$this->sval($row,$headers,$cands);
        return $v!==null ? (int)preg_replace('/[^\d\-]/','',$v) : 0;
    }
}