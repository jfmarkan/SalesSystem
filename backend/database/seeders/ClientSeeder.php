<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

class ClientSeeder extends Seeder
{
    private const ABORT_ON_ERROR = false;

    public function run(): void
    {
        $file = database_path('seeders/data/tblClients.csv');
        if (!is_file($file)) { $this->command->error("CSV not found: $file"); return; }

        $classIds = DB::table('classifications')->pluck('id')->toArray();
        if (empty($classIds)) { $this->command->error('No hay classifications.'); return; }

        $fallbackClassId = DB::table('classifications')->where('id',4)->value('id');
        if (!$fallbackClassId) $fallbackClassId = min($classIds);

        [$headers, $rows] = $this->readCsv($file);
        $cols = Schema::getColumnListing('clients');
        $has  = fn(string $c) => in_array($c,$cols,true);

        $now = Carbon::now();
        $inserted=0; $updated=0; $skipped=0; $clsFallbacks=0; $errors=0;

        foreach ($rows as $i=>$row) {
            $line = $i+2;

            $cgn  = $this->ival($row,$headers,['client_group_number','clientgroupnumber','ClientGroupNumber']);
            $name = $this->sval($row,$headers,['client_name','name','ClientName','Client']);
            $clsR = $this->sval($row,$headers,['classification_id','classificationid','ClassificationID']);

            if (!$cgn || !$name) {
                $skipped++; $this->command->warn("L{$line} SKIP: falta CGN o nombre"); continue;
            }

            $clsId = ($clsR!==null && preg_match('/-?\d+/', $clsR)) ? (int)$clsR : 0;
            if (!$clsId || !in_array($clsId,$classIds,true)) { $clsId = $fallbackClassId; $clsFallbacks++; }

            $payload = [];
            if ($has('client_group_number')) $payload['client_group_number']=$cgn;
            if ($has('client_name'))         $payload['client_name']=$name;
            if ($has('classification_id'))   $payload['classification_id']=$clsId;

            if (!isset($payload['client_group_number']) || !isset($payload['client_name']) || !isset($payload['classification_id'])) {
                $skipped++; $this->command->warn("L{$line} SKIP: columnas requeridas ausentes"); continue;
            }

            try {
                $exists = DB::table('clients')->where('client_group_number',$cgn)->exists();
                if ($exists) {
                    $upd = [
                        'client_name'       => $payload['client_name'],
                        'classification_id' => $payload['classification_id'],
                    ];
                    if ($has('updated_at')) $upd['updated_at'] = $now;
                    DB::table('clients')->where('client_group_number',$cgn)->update($upd);
                    $updated++;
                } else {
                    if ($has('created_at')) $payload['created_at']=$now;
                    if ($has('updated_at')) $payload['updated_at']=null;
                    if ($has('deleted_at')) $payload['deleted_at']=null;
                    DB::table('clients')->insert($payload);
                    $inserted++;
                }
            } catch (QueryException $e) {
                $errors++;
                $this->command->error("L{$line} ERROR: ".$e->getMessage());
                $this->command->line('Row='.json_encode($payload));
                if (self::ABORT_ON_ERROR) throw $e;
            }
        }

        $processed = $inserted+$updated+$skipped+$errors;
        $this->command->info("Clients: processed={$processed}, inserted={$inserted}, updated={$updated}, skipped={$skipped}, cls_fallbacks={$clsFallbacks}, errors={$errors}");
    }

    private function readCsv(string $path): array
    {
        $fh=fopen($path,'rb'); if(!$fh) return [[],[]];
        $first=fgets($fh); if($first===false){ fclose($fh); return [[],[]]; }
        $first=preg_replace('/^\xEF\xBB\xBF/','',$first);
        $semi=substr_count($first,';'); $coma=substr_count($first,','); $delim=($semi>$coma)?';':',';
        $headers=array_map(fn($h)=>$this->norm((string)$h), str_getcsv($first,$delim));
        $rows=[]; while(($line=fgetcsv($fh,0,$delim))!==false){
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
        if (!preg_match('/-?\d+/', $v, $m)) return 0;
        return (int)$m[0];
    }
}