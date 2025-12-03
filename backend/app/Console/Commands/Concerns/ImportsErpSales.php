<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;

trait ImportsErpSales
{
    protected int $roundDecimalsCubicMeters = 3;
    protected int $roundDecimalsSalesUnits  = 3;
    protected int $roundDecimalsEuros       = 2;

    protected function runImport(
        string $from,
        bool $dryRun   = false,
        string $source = 'manual',
        bool $print    = false,
        int $printMax  = 50
    ): array {
        $from = \Carbon\Carbon::parse($from)->toDateString();

        $cpcMap = [];
        foreach (DB::table('client_profit_centers')->select('id','client_group_number','profit_center_code')->get() as $r) {
            $cg5 = strtoupper(trim((string)$r->client_group_number));
            $pc3 = strtoupper(substr(trim((string)$r->profit_center_code), 0, 3));
            $cpcMap["{$cg5}|{$pc3}"] = (int)$r->id;
        }

        $sql = <<<SQL
DECLARE @from DATE = ?;

WITH NowD AS (
    SELECT CAST(GETDATE() AS date) AS Today
),
PMap AS (
    SELECT DISTINCT
        P.ID AS PartnerID,
        CC.Code AS ClientGroupNumber
    FROM STBSPOT.dbo.Partner P
    LEFT JOIN STBSPOT.dbo.CustomerClassification001 CC
        ON CC.ID = P.CustomerClassification001_ID
    WHERE
        P.IsCustomer_0004 = 1
        AND P.CustomerStatus = 1
        AND CC.Code IS NOT NULL
),
BaseReal AS (
    SELECT
        CASE
            WHEN LEFT(SI.ProfitCenter, 3) = '190' AND SI.ProductGroup_ID IN (26,27,30,585) THEN '130'
            WHEN LEFT(SI.ProfitCenter, 3) = '190' AND SI.ProductGroup_ID IN (182,183,573) THEN '160'
            WHEN LEFT(SI.ProfitCenter, 3) = '172' THEN '170'
            ELSE LEFT(SI.ProfitCenter, 3)
        END AS ProfitCenterCode,
        PM.ClientGroupNumber,
        YEAR(D.DateSort) AS Year,
        MONTH(D.DateSort) AS Month,
        SUM(FD.QuantitySalesUOM1)       AS Real_SalesUnits,
        SUM(FD.NetVolumeCubicMeter)     AS Real_CubicMeters,
        SUM(FD.NetAmount)               AS Real_Euros
    FROM STBSPOT.olap.FactSalesDaily FD
    JOIN STBSPOT.olap.DimDate D           ON D.ID  = FD.Date_ID
    JOIN STBSPOT.olap.DimSalesItemData SI ON SI.ID = FD.Item_ID
    JOIN STBSPOT.olap.DimCustomer C       ON C.ID  = FD.OrderPartner_ID
    JOIN PMap PM                          ON PM.PartnerID = C.ID
    WHERE
        D.DateSort >= @from
        AND D.DateSort < DATEADD(DAY, 1, (SELECT Today FROM NowD))
    GROUP BY
        CASE
            WHEN LEFT(SI.ProfitCenter, 3) = '190' AND SI.ProductGroup_ID IN (26,27,30,585) THEN '130'
            WHEN LEFT(SI.ProfitCenter, 3) = '190' AND SI.ProductGroup_ID IN (182,183,573) THEN '160'
            WHEN LEFT(SI.ProfitCenter, 3) = '172' THEN '170'
            ELSE LEFT(SI.ProfitCenter, 3)
        END,
        PM.ClientGroupNumber,
        YEAR(D.DateSort),
        MONTH(D.DateSort)
),
BaseFut AS (
    SELECT
        CASE
            WHEN LEFT(SI.ProfitCenter, 3) = '190' AND SI.ProductGroup_ID IN (26,27,30,585) THEN '130'
            WHEN LEFT(SI.ProfitCenter, 3) = '190' AND SI.ProductGroup_ID IN (182,183,573) THEN '160'
            WHEN LEFT(SI.ProfitCenter, 3) = '172' THEN '170'
            ELSE LEFT(SI.ProfitCenter, 3)
        END AS ProfitCenterCode,
        PM.ClientGroupNumber,
        YEAR(D.DateSort) AS Year,
        MONTH(D.DateSort) AS Month,
        SUM(FV.FutureQuantitySalesUom1) AS Future_SalesUnits,
        SUM(FV.NetVolumeCubicMeter)     AS Future_CubicMeters,
        SUM(FV.NetAmountDomestic)       AS Future_Euros
    FROM STBSPOT.olap.FactSalesOrderFutureValues FV
    JOIN STBSPOT.olap.DimDate D           ON D.ID  = FV.Date_ID
    JOIN STBSPOT.olap.DimSalesItemData SI ON SI.ID = FV.Item_ID
    JOIN PMap PM                          ON PM.PartnerID = FV.Partner_ID
    WHERE
        D.DateSort >= (SELECT Today FROM NowD)
    GROUP BY
        CASE
            WHEN LEFT(SI.ProfitCenter, 3) = '190' AND SI.ProductGroup_ID IN (26,27,30,585) THEN '130'
            WHEN LEFT(SI.ProfitCenter, 3) = '190' AND SI.ProductGroup_ID IN (182,183,573) THEN '160'
            WHEN LEFT(SI.ProfitCenter, 3) = '172' THEN '170'
            ELSE LEFT(SI.ProfitCenter, 3)
        END,
        PM.ClientGroupNumber,
        YEAR(D.DateSort),
        MONTH(D.DateSort)
),
Combined AS (
    SELECT
        COALESCE(R.ProfitCenterCode,  F.ProfitCenterCode)  AS ProfitCenterCode,
        COALESCE(R.ClientGroupNumber, F.ClientGroupNumber) AS ClientGroupNumber,
        COALESCE(R.Year,              F.Year)              AS Year,
        COALESCE(R.Month,             F.Month)             AS Month,
        ISNULL(R.Real_CubicMeters,  0) + ISNULL(F.Future_CubicMeters, 0)  AS CubicMeters,
        ISNULL(R.Real_Euros,        0) + ISNULL(F.Future_Euros,       0)  AS Euros,
        ISNULL(R.Real_SalesUnits,   0) + ISNULL(F.Future_SalesUnits,  0)  AS SalesUnits
    FROM BaseReal R
    FULL OUTER JOIN BaseFut F
        ON R.ProfitCenterCode  = F.ProfitCenterCode
        AND R.ClientGroupNumber = F.ClientGroupNumber
        AND R.Year             = F.Year
        AND R.Month            = F.Month
)
SELECT
    ProfitCenterCode,
    ClientGroupNumber,
    Year,
    Month,
    CubicMeters,
    Euros,
    SalesUnits
FROM Combined
WHERE ProfitCenterCode IN ('110','130','140','141','142','143','144','160','170','171','173','174','175')
ORDER BY ProfitCenterCode, ClientGroupNumber, Year, Month;
SQL;

        $rows = DB::connection('sqlsrv_stbspot')->select($sql, [$from]);

        // Acumuladores con máxima precisión
        $seen = 0; $missing = 0; $shown = 0;
        $acc = [];

        foreach ($rows as $r) {
            $pc3   = strtoupper(substr(trim((string)$r->ProfitCenterCode), 0, 3));
            $cg5   = strtoupper(trim((string)$r->ClientGroupNumber)); // debería ser el client_group_number
            $year  = (int)$r->Year;
            $month = (int)$r->Month;

            $cubicMeters = (float) $r->CubicMeters;
            $salesUnits  = (float) $r->SalesUnits;
            $euros       = (float) $r->Euros;

            $errs = [];
            if ($cg5 === '' || strlen($cg5) !== 5) $errs[] = 'INVALID_CLIENT_GROUP_CODE';
            if ($pc3 === '' || strlen($pc3) !== 3) $errs[] = 'INVALID_PROFIT_CENTER';
            if ($month < 1 || $month > 12)        $errs[] = 'INVALID_MONTH';
            if (!is_finite($cubicMeters))         $errs[] = 'INVALID_CUBIC_METERS';
            if (!is_finite($salesUnits))          $errs[] = 'INVALID_SALES_UNITS';
            if (!is_finite($euros))               $errs[] = 'INVALID_EUROS';

            $cpcId = $cpcMap["{$cg5}|{$pc3}"] ?? null;

            if (!$cpcId) {
                // Intentar crear automáticamente el CPC como antes
                $client = DB::table('clients')
                    ->where('client_group_number', $cg5)
                    ->first();

                if ($client) {
                    $newCpcId = DB::table('client_profit_centers')->insertGetId([
                        'client_group_number' => $cg5,
                        'profit_center_code'  => $pc3,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);

                    // Buscar username del responsable
                    $username = $client->group_responsible;
                    $user = DB::table('users')->where('username', $username)->first();

                    if ($user) {
                        $teamMember = DB::table('team_members')->where('user_id', $user->id)->first();

                        if ($teamMember) {
                            DB::table('assignments')->insert([
                                'client_profit_center_id' => $newCpcId,
                                'user_id'                 => $user->id,
                                'team_id'                 => $teamMember->team_id,
                                'created_at'              => now(),
                                'updated_at'              => now(),
                            ]);

                            if ($print && $shown < $printMax) {
                                $this->info(sprintf(
                                    'ASSIGNED ▶ cpc_id=%s user=%s (id=%s) team_id=%s',
                                    $newCpcId, $username, $user->id, $teamMember->team_id
                                ));
                                $shown++;
                            }
                        } else {
                            if ($print && $shown < $printMax) {
                                $this->warn(sprintf(
                                    'ASSIGN_SKIPPED ▶ No team found for user %s (id=%s)',
                                    $username, $user->id
                                ));
                                $shown++;
                            }
                        }
                    } else {
                        if ($print && $shown < $printMax) {
                            $this->warn(sprintf(
                                'ASSIGN_SKIPPED ▶ Responsible user not found: %s',
                                $username
                            ));
                            $shown++;
                        }
                    }

                    // Actualizar el mapa para continuar el procesamiento
                    $cpcMap["{$cg5}|{$pc3}"] = $newCpcId;
                    $cpcId = $newCpcId;

                    if ($print && $shown < $printMax) {
                        $this->info(sprintf(
                            'CREATED CPC ▶ pc=%s cg5=%s → cpc_id=%s',
                            $pc3, $cg5, $newCpcId
                        ));
                        $shown++;
                    }
                } else {
                    $errs[] = 'CPC_NOT_FOUND_CLIENT_MISSING';
                }
            }

            if ($errs) {
                $missing++;
                if ($print && $shown < $printMax) {
                    $this->warn(sprintf(
                        'REJECT ▶ pc=%s cg5=%s y=%d m=%02d m3=%s vk=%s eur=%s reason=%s',
                        $pc3, $cg5, $year, $month,
                        is_finite($cubicMeters) ? number_format($cubicMeters, 6, '.', '') : 'NULL',
                        is_finite($salesUnits)  ? number_format($salesUnits,  6, '.', '') : 'NULL',
                        is_finite($euros)       ? number_format($euros,       6, '.', '') : 'NULL',
                        implode(',', $errs)
                    ));
                    $shown++;
                }
                continue;
            }

            $seen++;
            $key = "{$cpcId}|{$year}|{$month}";

            if (!isset($acc[$key])) {
                $acc[$key] = [
                    'client_profit_center_id' => $cpcId,
                    'fiscal_year'             => $year,
                    'month'                   => $month,
                    'cubic_meters'            => 0.0,
                    'sales_units'             => 0.0,
                    'euros'                   => 0.0,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ];
            }

            // Acumular sin redondear
            $acc[$key]['cubic_meters'] += $cubicMeters;
            $acc[$key]['sales_units']  += $salesUnits;
            $acc[$key]['euros']        += $euros;
        }

        // Upsert (redondeo sólo ahora)
        $upserts = 0;
        if (!$dryRun && $acc) {
            $rowsToWrite = array_map(function (array $r) {
                $r['cubic_meters'] = round((float)$r['cubic_meters'], $this->roundDecimalsCubicMeters);
                $r['sales_units']  = round((float)$r['sales_units'],  $this->roundDecimalsSalesUnits);
                $r['euros']        = round((float)$r['euros'],        $this->roundDecimalsEuros);
                return $r;
            }, array_values($acc));

            foreach (array_chunk($rowsToWrite, 1000) as $chunk) {
                $upserts += $this->safeUpsertConsole($chunk, $print, $printMax, $shown);
            }
        }

        return compact('from','seen','upserts','missing') + ['shown' => $shown];
    }

    protected function safeUpsertConsole(array $rows, bool $print, int $printMax, int &$shown): int
    {
        try {
            DB::table('sales')->upsert(
                $rows,
                ['client_profit_center_id','fiscal_year','month'],
                ['cubic_meters','sales_units','euros','updated_at']
            );
            return count($rows);
        } catch (\Throwable $e) {
            $ok = 0;
            foreach ($rows as $row) {
                try {
                    // Re-asegurar redondeo final (idempotente)
                    if (isset($row['cubic_meters'])) {
                        $row['cubic_meters'] = round((float)$row['cubic_meters'], $this->roundDecimalsCubicMeters);
                    }
                    if (isset($row['sales_units'])) {
                        $row['sales_units']  = round((float)$row['sales_units'],  $this->roundDecimalsSalesUnits);
                    }
                    if (isset($row['euros'])) {
                        $row['euros']        = round((float)$row['euros'],        $this->roundDecimalsEuros);
                    }

                    DB::table('sales')->upsert(
                        [$row],
                        ['client_profit_center_id','fiscal_year','month'],
                        ['cubic_meters','sales_units','euros','updated_at']
                    );
                    $ok++;
                } catch (\Throwable $e2) {
                    if ($print && $shown < $printMax) {
                        $this->error(sprintf(
                            'DB_ERROR ▶ cpc_id=%s y=%s m=%s m3=%s vk=%s eur=%s err=%s',
                            $row['client_profit_center_id'] ?? 'NULL',
                            $row['fiscal_year'] ?? 'NULL',
                            $row['month'] ?? 'NULL',
                            isset($row['cubic_meters']) ? number_format((float)$row['cubic_meters'], 6, '.', '') : 'NULL',
                            isset($row['sales_units'])  ? number_format((float)$row['sales_units'],  6, '.', '') : 'NULL',
                            isset($row['euros'])        ? number_format((float)$row['euros'],        6, '.', '') : 'NULL',
                            mb_substr($e2->getMessage(), 0, 200)
                        ));
                        $shown++;
                    }
                }
            }
            return $ok;
        }
    }
}
