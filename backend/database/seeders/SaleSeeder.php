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
        $now       = Carbon::now();
        $batch     = [];
        $inserted  = 0;
        $skipped   = 0;
        $duplicates= 0;

        // límites de INT firmado
        $MIN = -2147483648; $MAX = 2147483647;

        // Acumuladores de fallas resumidas
        $failedCpc          = []; // client_profit_center_id que no se pudieron cargar
        $failedAssignments  = []; // assignment_id de filas que no pasaron (si viene en el CSV)

        foreach ($rows as $row) {
            $cpcId        = $this->ival($row,$headers,['client_profit_center_id','clientprofitcenterid','ClientProfitCenterID']);
            $year         = $this->ival($row,$headers,['fiscal_year','sales_year','salesyear','SalesYear']);
            $month        = $this->ival($row,$headers,['month','sales_month','salesmonth','SalesMonth']);
            // opcional: si viene en el CSV, solo para reporte
            $assignmentId = $this->ival($row,$headers,['assignment_id','assignmentid','AssignmentID']);

            $volRaw = $this->sval($row,$headers,['volume','sales_value','SalesValue','amount','Volume']);
            $volume = is_null($volRaw) ? null : (int) round((float) str_replace(',','.', $volRaw));

            // Validaciones mínimas
            if (!$year || !$month || is_null($volume)) {
                $skipped++;
                if ($assignmentId > 0) $failedAssignments[] = $assignmentId;
                elseif ($cpcId > 0)    $failedCpc[] = $cpcId;
                continue;
            }

            // Validar CPC existente
            if (!$cpcId || !DB::table('client_profit_centers')->where('id',$cpcId)->exists()) {
                $skipped++;
                if ($cpcId > 0) $failedCpc[] = $cpcId;
                if ($assignmentId > 0) $failedAssignments[] = $assignmentId;
                continue;
            }

            // Cap a rango INT
            if ($volume < $MIN) $volume = $MIN;
            if ($volume > $MAX) $volume = $MAX;

            // Dedupe (cpc, year, month)
            $exists = DB::table('sales')->where([
                'client_profit_center_id' => $cpcId,
                'fiscal_year'             => $year,
                'month'                   => $month,
            ])->exists();

            if ($exists) {
                $skipped++;
                $duplicates++;
                // No listamos nada acá para no mezclar "no pudo cargar" con "ya existía".
                continue;
            }

            $batch[] = [
                'client_profit_center_id' => $cpcId,
                'fiscal_year'             => $year,
                'month'                   => $month,
                'volume'                  => $volume, // puede ser negativo
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

        // Resumen
        $this->command->info("Sales: inserted={$inserted}, skipped={$skipped}, duplicates={$duplicates}");

        // Listados agregados (únicos)
        $failedCpc = array_values(array_unique(array_filter($failedCpc, fn($v)=>$v>0)));
        $failedAssignments = array_values(array_unique(array_filter($failedAssignments, fn($v)=>$v>0)));

        if (!empty($failedCpc)) {
            $this->command->warn('client_profit_center_id no cargados (únicos): '.implode(',', $failedCpc));
        }
        if (!empty($failedAssignments)) {
            $this->command->warn('assignment_id fallidos (únicos): '.implode(',', $failedAssignments));
        }
    }

    private function readCsv(string $path): array
    {
        $fh=fopen($path,'rb'); if(!$fh) return [[],[]];
        $first=fgets($fh); if($first===false) { fclose($fh); return [[],[]]; }
        $first=preg_replace('/^\xEF\xBB\xBF/','',$first);
        $semi=substr_count($first,';'); $coma=substr_count($first,',');
        $delim=($semi>$coma)?';':',';
        $headers=array_map(fn($h)=>$this->norm($h), str_getcsv($first,$delim));
        $rows=[];
        while(($line=fgetcsv($fh,0,$delim))!==false){
            if(count($line)===1 && trim((string)$line[0])==='') continue;
            if(count($line)<count($headers)) $line=array_pad($line,count($headers),null);
            $rows[]=$line;
        }
        fclose($fh); return [$headers,$rows];
    }

    private function norm(string $s): string
    {
        return strtolower(str_replace([' ','-'],'_',trim($s)));
    }

    private function sval(array $row,array $headers,array $cands): ?string
    {
        foreach($cands as $c){
            $k=$this->norm($c); $i=array_search($k,$headers,true);
            if($i!==false && isset($row[$i]) && $row[$i]!=='') return trim((string)$row[$i]);
        }
        return null;
    }

    private function ival(array $row,array $headers,array $cands): int
    {
        $v=$this->sval($row,$headers,$cands);
        return $v!==null ? (int)preg_replace('/[^\d\-]/','',$v) : 0;
    }
}