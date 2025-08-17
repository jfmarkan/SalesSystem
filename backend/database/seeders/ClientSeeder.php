<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/tblClients.csv');
        if (!is_file($path)) {
            $this->command?->error("CSV not found: $path");
            return;
        }

        $h = fopen($path, 'rb');
        if (!$h) {
            $this->command?->error("Cannot open $path");
            return;
        }

        // First non-empty line → detect delimiter + headers
        $first = '';
        while (($line = fgets($h)) !== false) {
            $line = trim($line, "\r\n");
            if ($line !== '') { $first = $line; break; }
        }
        if ($first === '') { fclose($h); $this->command?->warn('Empty CSV'); return; }

        $delim   = $this->detectDelimiter($first);
        $headers = array_map(fn($h) => Str::of($h)->trim()->snake()->toString(), str_getcsv($first, $delim));

        $rows = [];
        while (($row = fgetcsv($h, 0, $delim)) !== false) {
            if (count($row) === 1 && trim($row[0]) === '') continue;

            $assoc = [];
            foreach ($headers as $i => $key) {
                $assoc[$key] = $this->toUtf8($row[$i] ?? null); // preserve umlauts
            }

            $id  = (int) ($assoc['client_group_number'] ?? $assoc['id'] ?? 0);
            $name = $assoc['client_name'] ?? $assoc['name'] ?? null;
            $classificationId = isset($assoc['classification_id']) && $assoc['classification_id'] !== '' ? (int)$assoc['classification_id'] : null;

            if ($id === 0 || $name === null) continue;

            $rows[] = [
                'client_group_number' => $id,
                'client_name'         => $name,
                'classification_id'   => $classificationId,
                'created_at'          => now(),
                'updated_at'          => now(),
            ];
        }
        fclose($h);

        if (empty($rows)) { $this->command?->warn('No rows to import.'); return; }

        DB::table('clients')->upsert(
            $rows,
            ['client_group_number'],
            ['client_name','classification_id','updated_at']
        );

        $this->command?->info('Clients imported: '.count($rows));
    }

    private function detectDelimiter(string $line): string
    {
        $c = substr_count($line, ',');
        $s = substr_count($line, ';');
        return $s > $c ? ';' : ',';
    }

    private function toUtf8(?string $v): ?string
    {
        if ($v === null) return null;
        $enc = mb_detect_encoding($v, ['UTF-8','ISO-8859-1','Windows-1252'], true) ?: 'UTF-8';
        return $enc === 'UTF-8' ? $v : mb_convert_encoding($v, 'UTF-8', $enc);
    }
}
