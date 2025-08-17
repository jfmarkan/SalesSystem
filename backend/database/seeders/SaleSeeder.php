<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('seeders/data/tblSales.csv');
        if (!is_file($file)) {
            $this->command->error("CSV not found: $file");
            return;
        }

        [$headers, $rows] = $this->readCsv($file);
        $now = Carbon::now();
        $batch = [];
        $inserted = 0; $skipped = 0;

        // max para UNSIGNED INT
        $MAX = 4294967295;

        foreach ($rows as $row) {
            $cpcId = $this->ival($row,$headers,['client_profit_center_id','clientprofitcenterid','ClientProfitCenterID']);
            $year  = $this->ival($row,$headers,['fiscal_year','sales_year','salesyear','SalesYear']);
            $month = $this->ival($row,$headers,['month','sales_month','salesmonth','SalesMonth']);

            $volRaw = $this->sval($row,$headers,['volume','sales_value','SalesValue','amount','Volume']);
            $volume = is_null($volRaw) ? null : (int) round((float) str_replace(',','.', $volRaw));

            if (!$year || !$month || is_null($volume)) { $skipped++; continue; }

            if (!$cpcId || !DB::table('client_profit_centers')->where('id',$cpcId)->exists()) {
                $this->command->warn("SKIP CPC inexistente: cpc_id={$cpcId}, y={$year}, m={$month}");
                $skipped++; continue;
            }

            // Normalización para unsigned int
            if ($volume < 0) {
                $this->command->warn("Volumen negativo -> 0 (cpc_id={$cpcId}, y={$year}, m={$month}, v={$volume})");
                $volume = 0;
            } elseif ($volume > $MAX) {
                $this->command->warn("Volumen > {$MAX} -> cap (cpc_id={$cpcId}, y={$year}, m={$month}, v={$volume})");
                $volume = $MAX;
            }

            // Dedupe (cpc, year, month)
            $exists = DB::table('sales')->where([
                'client_profit_center_id' => $cpcId,
                'fiscal_year'             => $year,
                'month'                   => $month,
            ])->exists();
            if ($exists) { $skipped++; continue; }

            $batch[] = [
                'client_profit_center_id' => $cpcId,
                'fiscal_year'             => $year,
                'month'                   => $month,
                'volume'                  => $volume,
                'created_at'              => $now,
                'updated_at'              => null,
                'deleted_at'              => null,
            ];

            if (count($batch) >= 200) {
                DB::table('sales')->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table('sales')->insert($batch);
            $inserted += count($batch);
        }

        $this->command->info("Sales: inserted={$inserted}, skipped={$skipped}");
    }

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
