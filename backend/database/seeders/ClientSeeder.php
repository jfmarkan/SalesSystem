<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('seeders/data/tblClients.csv'); // ajusta si tu CSV se llama distinto
        if (!is_file($file)) { $this->command->error("CSV not found: $file"); return; }

        // Precondición: classifications cargadas
        $classIds = DB::table('classifications')->pluck('id')->toArray();
        if (empty($classIds)) { $this->command->error('No hay classifications.'); return; }
        $classSet = array_fill_keys($classIds, true);
        $defaultClassId = min($classIds);

        // Columnas reales en DB
        $cols = Schema::getColumnListing('clients');
        $has = fn(string $c) => in_array($c, $cols, true);

        // CSV
        [$headers, $rows] = $this->readCsv($file);

        $batch = [];
        $processed = 0; $upserted = 0; $skipped = 0; $fallbacks = 0;
        $now = Carbon::now();

        foreach ($rows as $row) {
            $cgn  = $this->ival($row,$headers,['client_group_number','clientgroupnumber','ClientGroupNumber']);
            $name = $this->sval($row,$headers,['client_name','name','ClientName','Client']);
            $clsR = $this->sval($row,$headers,['classification_id','classificationid','ClassificationID']);

            if (!$cgn || !$name) { $skipped++; continue; }

            $clsId = $clsR !== null ? $this->toInt($clsR) : 0;
            if (!$clsId || !isset($classSet[$clsId])) { $clsId = $defaultClassId; $fallbacks++; }

            // Payload mínimo seguro
            $payload = [];
            if ($has('client_group_number')) $payload['client_group_number'] = $cgn;
            if ($has('client_name'))         $payload['client_name']         = $name;
            if ($has('classification_id'))   $payload['classification_id']   = $clsId;
            if ($has('created_at'))          $payload['created_at']          = $now;
            if ($has('updated_at'))          $payload['updated_at']          = null;
            if ($has('deleted_at'))          $payload['deleted_at']          = null;

            // Si falta alguna de las 3 claves mínimas, salteá
            if (!isset($payload['client_group_number']) || !isset($payload['client_name']) || !isset($payload['classification_id'])) {
                $skipped++; continue;
            }

            $batch[] = $payload;
            $processed++;

            if (count($batch) >= 1000) {
                $upserted += $this->flush($batch, $has);
                $batch = [];
            }
        }

        if ($batch) $upserted += $this->flush($batch, $has);

        $this->command->info("Clients imported: {$processed} (upserted={$upserted}, skipped={$skipped}, cls_fallbacks={$fallbacks})");
    }

    private function flush(array $batch, callable $has): int
    {
        try {
            // Clave única por CGN si existe; si no, insertOrIgnore
            if ($has('client_group_number')) {
                $updateCols = array_keys($batch[0]);
                $updateCols = array_values(array_diff($updateCols, ['client_group_number','created_at']));
                return DB::table('clients')->upsert($batch, ['client_group_number'], $updateCols);
            }
            return DB::table('clients')->insertOrIgnore($batch);
        } catch (QueryException $e) {
            $sample = $batch[0] ?? [];
            $this->command->error("DB ERROR: ".$e->getMessage());
            $this->command->line("Sample payload: ".json_encode($sample));
            // Intenta con FK off como último recurso
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                $r = DB::table('clients')->insertOrIgnore($batch);
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                return $r;
            } catch (QueryException $e2) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                throw $e2;
            }
        }
    }

    // --- helpers CSV / parseo ---
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
        return $v!==null ? $this->toInt($v) : 0;
    }
    private function toInt(string $v): int {
        if (!preg_match('/-?\d+/', $v, $m)) return 0;
        return (int)$m[0];
    }
}