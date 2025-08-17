<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClientProfitCenterSeeder extends Seeder
{
    /**
     * Seed client_profit_centers from CSV.
     * - CSV path: database/seeders/data/tblClientProfitCenterPivot.csv
     * - Assumes headers exist. We accept both snake_case and Access-style (ClientGroupNumber, ProfitCenterCode).
     * - id is AUTO; created_at = now(); updated_at/deleted_at = null.
     * - Ignores duplicate (client_group_number, profit_center_code) pairs.
     * - Keeps UTF-8 characters intact (ensure your CSV is saved as UTF-8).
     */
    public function run(): void
    {
        $path = database_path('seeders/data/tblClientProfitCenterPivot.csv');
        if (!is_file($path)) {
            $this->command->warn("CSV not found: {$path}");
            return;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->command->warn("Cannot open CSV: {$path}");
            return;
        }

        // Detect delimiter from the first line
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            $this->command->warn("Empty CSV: {$path}");
            return;
        }
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);

        // Read header
        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header || count($header) === 0) {
            fclose($handle);
            $this->command->warn("CSV has no header: {$path}");
            return;
        }

        // Normalize header keys to snake_case-ish
        $norm = fn ($s) => strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)$s)));
        $keys = array_map($norm, $header);

        $now = Carbon::now();
        $batch = [];
        $seen  = []; // to skip duplicate pairs from the CSV
        $rows  = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Skip empty lines
            if (count($row) === 1 && trim((string)$row[0]) === '') continue;

            // Pad row if shorter than header
            if (count($row) < count($keys)) {
                $row = array_pad($row, count($keys), null);
            }

            $assoc = array_combine($keys, $row) ?: [];

            // Accept both snake_case and Access-style headers
            $clientGroup =
                $assoc['client_group_number'] ??
                $assoc['clientgroupnumber'] ??
                $assoc['client_groupno'] ??
                $assoc['clientno'] ??
                null;

            $pcCode =
                $assoc['profit_center_code'] ??
                $assoc['profitcentercode'] ??
                $assoc['pc_code'] ??
                null;

            if ($clientGroup === null || $pcCode === null) {
                continue; // missing required data
            }

            $clientGroup = (int) $clientGroup;
            $pcCode      = (int) $pcCode;

            if ($clientGroup === 0 || $pcCode === 0) {
                continue; // invalid values
            }

            $pairKey = $clientGroup . '-' . $pcCode;
            if (isset($seen[$pairKey])) {
                continue; // skip duplicates within CSV
            }
            $seen[$pairKey] = true;

            $batch[] = [
                'client_group_number' => $clientGroup,
                'profit_center_code'  => $pcCode,
                'created_at'          => $now,
                'updated_at'          => null,
                'deleted_at'          => null,
            ];
            $rows++;

            // Flush in chunks
            if (count($batch) >= 1000) {
                DB::table('client_profit_centers')->insertOrIgnore($batch);
                $batch = [];
            }
        }
        fclose($handle);

        if (!empty($batch)) {
            DB::table('client_profit_centers')->insertOrIgnore($batch);
        }

        $this->command->info("Client / ProfitCenter relations imported: {$rows}");
    }
}