<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class SeasonalitySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/tblSeasonalities.csv');
        if (!is_file($path)) {
            $this->command->error("CSV not found: {$path}");
            return;
        }

        $fh = fopen($path, 'r');
        if (!$fh) {
            $this->command->error("Cannot open CSV: {$path}");
            return;
        }

        // Detect delimiter
        $probe = fgets($fh);
        if ($probe === false) {
            fclose($fh);
            $this->command->warn('No rows to import (empty file).');
            return;
        }
        $delimiter = (substr_count($probe, ';') > substr_count($probe, ',')) ? ';' : ',';
        rewind($fh);

        // Read header
        $headers = fgetcsv($fh, 0, $delimiter);
        if (!$headers) {
            fclose($fh);
            $this->command->warn('No rows to import (missing header).');
            return;
        }

        // Normalize helper (same for CSV headers and DB cols) → collapse to alnum
        $norm = function (string $s): string {
            $s = ltrim($s, "\xEF\xBB\xBF");   // strip BOM
            $s = trim($s);
            $s = mb_strtolower($s);
            // keep umlauts in data (we only normalize keys for matching)
            $s = str_replace(['ä','ö','ü','ß'], ['ae','oe','ue','ss'], $s);
            // drop separators/spaces/underscores/dots
            return preg_replace('/[^a-z0-9]+/u', '', $s);
        };

        // Real columns of the table
        $table = 'seasonalities';
        $realCols = Schema::getColumnListing($table);

        // Build a map of normalized db columns
        $normDb = [];
        foreach ($realCols as $col) {
            $normDb[$norm($col)] = $col; // normalized → real
        }

        // Map each CSV header index to a real DB column (if possible)
        $idxToCol = [];
        foreach ($headers as $i => $h) {
            $key = $norm((string)$h);
            $idxToCol[$i] = $normDb[$key] ?? null; // null = ignore column
        }

        $rows = [];
        $line = 1;

        while (($data = fgetcsv($fh, 0, $delimiter)) !== false) {
            $line++;

            // skip fully blank lines
            if (!array_filter($data, fn($v) => trim((string)$v) !== '')) {
                continue;
            }

            $row = [
                'created_at' => now(),
                'updated_at' => now(),
            ];

            foreach ($data as $i => $val) {
                $col = $idxToCol[$i] ?? null;
                if (!$col) continue;

                // Preserve umlauts in values; only cast numeric-looking values
                $v = trim((string)$val);
                // accept comma decimal
                $numCandidate = str_replace([' ', ','], ['', '.'], $v);
                if ($numCandidate !== '' && is_numeric($numCandidate)) {
                    $row[$col] = (float)$numCandidate;
                } else {
                    $row[$col] = $v;
                }
            }

            // If table has auto-increment id, omit empty/zero id from CSV
            if (array_key_exists('id', $row) && ($row['id'] === '' || $row['id'] === null || (int)$row['id'] === 0)) {
                unset($row['id']);
            }

            // Require at least one non-timestamp column to avoid inserting empties
            $payload = array_diff_key($row, array_flip(['created_at','updated_at']));
            if (empty($payload)) {
                $this->command->warn("Skipped line {$line}: no mappable columns.");
                continue;
            }

            $rows[] = $row;

            if (count($rows) >= 500) {
                DB::table($table)->insert($rows);
                $rows = [];
            }
        }

        fclose($fh);

        if ($rows) {
            DB::table($table)->insert($rows);
        }

        $this->command->info('Seasonalities imported: '.count($rows));
    }
}