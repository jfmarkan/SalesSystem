<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExtraQuotaAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/extra_quota_assignments.csv');
        if (!is_file($path)) {
            $this->command->error("❌ File not found in: $path");
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->command->error("❌ File could not be opened.");
            return;
        }

        // Detectar delimitador (coma o punto y coma) y remover BOM
        $firstLine = fgets($handle);
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        $headers = array_map('trim', str_getcsv($firstLine, $delimiter));

        // Validar columnas esperadas
        $expected = ['fiscal_year','profit_center_code','user_id','volume','is_published','assignment_date'];
        $missing = array_diff($expected, $headers);
        if (!empty($missing)) {
            fclose($handle);
            $this->command->error("❌ Missing Columns in CSV file: " . implode(', ', $missing));
            $this->command->warn("Found Headers: " . implode(', ', $headers));
            return;
        }

        $colIndex = array_flip($headers);
        $rows = 0;
        $batch = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($this->isEmptyRow($row)) { continue; }

            $get = fn(string $key) => $row[$colIndex[$key]] ?? null;

            $volumeRaw = trim((string) $get('volume'));
            // Convertir "1.234,56" o "1234,56" a float
            $volumeNorm = str_replace(['.', ','], ['', '.'], $volumeRaw);

            $payload = [
                'fiscal_year'        => (int) trim((string) $get('fiscal_year')),
                'profit_center_code' => trim((string) $get('profit_center_code')),
                'user_id'            => (int) trim((string) $get('user_id')),
                'volume'             => (float) $volumeNorm,
                'is_published'       => $this->toBool($get('is_published')),
                'assignment_date'    => $this->toDate($get('assignment_date')),
                'created_at'         => now(),
                'updated_at'         => now(),
            ];

            $batch[] = $payload;
            $rows++;

            if (count($batch) >= 500) {
                DB::table('extra_quota_assignments')->insert($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            DB::table('extra_quota_assignments')->insert($batch);
        }

        $this->command->info("✅ Imported {$rows} rows in extra_quota_assignments from CSV file.");
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') return false;
        }
        return true;
    }

    private function toBool($value): bool
    {
        $v = strtolower(trim((string) $value));
        return in_array($v, ['1','true','t','yes','y','si','sí','on'], true);
    }

    private function toDate($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') return null;
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
