<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('seeders\data\tblAssignments.csv'); // ajustá si tu archivo se llama distinto
        if (!is_file($file)) {
            $this->command->error("CSV not found: $file");
            return;
        }

        [$headers, $rows] = $this->readCsv($file);
        $now = Carbon::now();

        $batch = [];
        $inserted = 0; $skipped = 0; $restored = 0;

        foreach ($rows as $row) {
            $cpcId  = $this->ival($row,$headers,[
                'client_profit_center_id','clientprofitcenterid','ClientProfitCenterID','cpc_id','CPCID'
            ]);
            $teamId = $this->ival($row,$headers,[
                'team_id','teamid','TeamID','team'
            ]);
            $userId = $this->ival($row,$headers,[
                'user_id','userid','UserID','user'
            ]);

            // Campos requeridos
            if (!$cpcId || !$teamId || !$userId) {
                $this->command->warn("SKIP: fila inválida (cpc={$cpcId}, team={$teamId}, user={$userId})");
                $skipped++; continue;
            }

            // Validación de FKs para evitar errores 1452
            if (!DB::table('client_profit_centers')->where('id',$cpcId)->exists()) {
                $this->command->warn("SKIP: CPC inexistente (cpc_id={$cpcId})");
                $skipped++; continue;
            }
            if (!DB::table('teams')->where('id',$teamId)->exists()) {
                $this->command->warn("SKIP: Team inexistente (team_id={$teamId})");
                $skipped++; continue;
            }
            if (!DB::table('users')->where('id',$userId)->exists()) {
                $this->command->warn("SKIP: User inexistente (user_id={$userId})");
                $skipped++; continue;
            }

            // Dedupe por (cpc, team, user) + restore si está soft-deleted
            $existing = DB::table('assignments')->where([
                'client_profit_center_id' => $cpcId,
                'team_id'                 => $teamId,
                'user_id'                 => $userId,
            ])->first();

            if ($existing) {
                if (!is_null($existing->deleted_at)) {
                    DB::table('assignments')->where('id',$existing->id)->update([
                        'deleted_at' => null,
                        'updated_at' => $now,
                    ]);
                    $restored++;
                } else {
                    $skipped++;
                }
                continue;
            }

            // Insert batch
            $batch[] = [
                'client_profit_center_id' => $cpcId,
                'team_id'                 => $teamId,
                'user_id'                 => $userId,
                'created_at'              => $now,
                'updated_at'              => null,
                'deleted_at'              => null,
            ];

            if (count($batch) >= 200) {
                DB::table('assignments')->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table('assignments')->insert($batch);
            $inserted += count($batch);
        }

        $this->command->info("Assignments: inserted=$inserted, restored=$restored, skipped=$skipped");
    }

    // --- helpers (mismo estilo) ---
    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'rb'); if(!$fh) return [[],[]];
        $first = fgets($fh); if ($first===false) return [[],[]];
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first); // BOM
        $semi = substr_count($first,';'); $coma = substr_count($first,',');
        $delim = ($semi > $coma) ? ';' : ',';
        $headers = array_map(fn($h)=>$this->norm($h), str_getcsv($first, $delim));
        $rows = [];
        while(($line = fgetcsv($fh, 0, $delim)) !== false){
            if (count($line)===1 && trim($line[0])==='') continue;
            if (count($line) < count($headers)) $line = array_pad($line, count($headers), null);
            $rows[] = $line;
        }
        fclose($fh);
        return [$headers,$rows];
    }

    private function norm(string $s): string
    {
        return strtolower(str_replace([' ','-'],'_',trim($s)));
    }

    private function sval(array $row, array $headers, array $cands): ?string
    {
        foreach($cands as $c){
            $k = $this->norm($c);
            $i = array_search($k, $headers, true);
            if($i !== false && isset($row[$i]) && $row[$i] !== '') return trim($row[$i]);
        }
        return null;
    }

    private function ival(array $row, array $headers, array $cands): int
    {
        $v = $this->sval($row,$headers,$cands);
        return $v !== null ? (int)preg_replace('/[^\d\-]/','',$v) : 0;
    }
}
