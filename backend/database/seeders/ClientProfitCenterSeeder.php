<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

class ClientProfitCenterSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/tblClientProfitCenterPivot.csv');
        if (!is_file($path)) { $this->command->error("CSV not found: {$path}"); return; }

        [$headers, $rows] = $this->readCsv($path);
        if (!$headers) { $this->command->error("CSV vacío o sin header"); return; }

        // Columna de código en profit_centers
        $pcCol = Schema::hasColumn('profit_centers','code') ? 'code'
               : (Schema::hasColumn('profit_centers','profit_center_code') ? 'profit_center_code' : null);
        if (!$pcCol) { $this->command->error("profit_centers no tiene code/profit_center_code"); return; }

        $hasCreatedAt = Schema::hasColumn('client_profit_centers','created_at');
        $hasUpdatedAt = Schema::hasColumn('client_profit_centers','updated_at');
        $hasDeletedAt = Schema::hasColumn('client_profit_centers','deleted_at');

        $now = Carbon::now();

        $total=0; $inserted=0; $dupDb=0; $badClient=0; $badPc=0; $errors=0;

        foreach ($rows as $i => $row) {
            $line = $i + 2; // header = línea 1

            $cgn = $this->ival($row,$headers,['client_group_number','clientgroupnumber','ClientGroupNumber']);
            $pc  = $this->ival($row,$headers,['profit_center_code','profitcentercode','pc_code','ProfitCenterCode']);

            if (!$cgn || !$pc) { 
                $this->command->warn("L{$line} SKIP: valores inválidos cgn={$cgn} pc={$pc}");
                continue;
            }

            // FK manuales
            $cExists = DB::table('clients')->where('client_group_number',$cgn)->exists();
            if (!$cExists) { $badClient++; $this->command->warn("L{$line} SKIP: cliente no existe CGN={$cgn}"); continue; }

            $pExists = DB::table('profit_centers')->where($pcCol,$pc)->exists();
            if (!$pExists) { $badPc++; $this->command->warn("L{$line} SKIP: profit center no existe PC={$pc}"); continue; }

            // Duplicado en DB
            $pairExists = DB::table('client_profit_centers')
                ->where('client_group_number',$cgn)
                ->where('profit_center_code',$pc)
                ->exists();
            if ($pairExists) { $dupDb++; $this->command->warn("L{$line} DUP en DB: CGN={$cgn}, PC={$pc}"); continue; }

            // Insert estricto
            $payload = [
                'client_group_number' => $cgn,
                'profit_center_code'  => $pc,
            ];
            if ($hasCreatedAt) $payload['created_at'] = $now;
            if ($hasUpdatedAt) $payload['updated_at'] = null;
            if ($hasDeletedAt) $payload['deleted_at'] = null;

            try {
                DB::table('client_profit_centers')->insert($payload);
                $inserted++;
            } catch (QueryException $e) {
                $errors++;
                $this->command->error("L{$line} ERROR: ".$e->getMessage());
                $this->command->line('Row='.json_encode($payload));
            }

            $total++;
        }

        $count = DB::table('client_profit_centers')->count();
        $minId = DB::table('client_profit_centers')->min('id');
        $maxId = DB::table('client_profit_centers')->max('id');

        $this->command->info("CPC: total={$total}, inserted={$inserted}, dup_db={$dupDb}, bad_client={$badClient}, bad_pc={$badPc}, errors={$errors}, table_count={$count}, id_range={$minId}..{$maxId}");
    }

    // --- helpers ---
    private function readCsv(string $path): array
    {
        $fh=fopen($path,'rb'); if(!$fh) return [[],[]];
        $first=fgets($fh); if($first===false){ fclose($fh); return [[],[]]; }
        $first=preg_replace('/^\xEF\xBB\xBF/','',$first);
        $semi=substr_count($first,';'); $coma=substr_count($first,',');
        $delim=($semi>$coma)?';':',';
        rewind($fh);

        $header=fgetcsv($fh,0,$delim);
        if(!$header){ fclose($fh); return [[],[]]; }
        $headers=array_map(fn($h)=>$this->norm((string)$h), $header);

        $rows=[];
        while(($line=fgetcsv($fh,0,$delim))!==false){
            if(count($line)===1 && trim((string)$line[0])==='') continue;
            if(count($line)<count($headers)) $line=array_pad($line,count($headers),null);
            $rows[]=$line;
        }
        fclose($fh);
        return [$headers,$rows];
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
        if (!preg_match('/-?\d+/', $v, $m)) return 0; // ojo: convierte '070'->70
        return (int)$m[0];
    }
}