<?php
// app/Console/Commands/Concerns/ImportsErpSales.php
//
// Change:
// - Keep full precision while summing.
// - Round ONLY when upserting to DB (default 2 decimals).
// - Everything else stays the same.

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;

trait ImportsErpSales
{
    /** Final rounding decimals (adjust if needed) */
    protected int $roundDecimals = 2;

    protected function runImport(string $from, bool $dryRun = false, string $source = 'manual', bool $print = false, int $printMax = 50): array
    {
        $from = \Carbon\Carbon::parse($from)->toDateString();

        // CPC map: "CG5|PC3" -> id
        $cpcMap = [];
        foreach (DB::table('client_profit_centers')->select('id','client_group_number','profit_center_code')->get() as $r) {
            $cg5 = strtoupper(trim((string)$r->client_group_number));
            $pc3 = strtoupper(substr(trim((string)$r->profit_center_code), 0, 3));
            $cpcMap["{$cg5}|{$pc3}"] = (int)$r->id;
        }

        // SQL (REAL + FUTURE) via Partner→CC, IsCustomer_0004=1, m³ vs UOM by PC, CompanyNumberInt<=20000
        $sql = <<<SQL
DECLARE @from DATE = ?;
WITH NowD AS (
    SELECT CAST(GETDATE() AS date) AS Today
),
PMap AS (
    SELECT DISTINCT
        P.ID AS PartnerID,
        RIGHT('000000' + LTRIM(RTRIM(CAST(P.Number AS varchar(6)))), 6) AS NumberLast6,
        LTRIM(RTRIM(CAST(CC.Code AS varchar(50))))  AS CompanyNumberStr,
        TRY_CONVERT(int, LTRIM(RTRIM(CAST(CC.Code AS varchar(50)))))    AS CompanyNumberInt,
        RIGHT('00000' + LTRIM(RTRIM(CAST(CC.Code AS varchar(50)))), 5)  AS CompanyGroup5
    FROM STBSPOT.dbo.Partner P
    LEFT JOIN STBSPOT.dbo.CustomerClassification001 CC
           ON CC.ID = P.CustomerClassification001_ID
    WHERE
        P.IsCustomer_0004 = 1
        AND CC.Code IS NOT NULL
        AND LTRIM(RTRIM(CAST(CC.Code AS varchar(50)))) <> ''
),
BaseReal AS (
    SELECT
        CASE
            WHEN LEFT(LTRIM(RTRIM(SI.ProfitCenter)),3) = '190' AND SI.ProductGroup_ID IN (26,27,30,585) THEN '130'
            WHEN LEFT(LTRIM(RTRIM(SI.ProfitCenter)),3) = '190' AND SI.ProductGroup_ID IN (182,183,573)  THEN '160'
            ELSE LEFT(LTRIM(RTRIM(SI.ProfitCenter)),3)
        END AS PC3,
        PM.CompanyNumberStr AS CompanyNumber,
        PM.CompanyNumberInt AS CompanyNumberInt,
        PM.CompanyGroup5    AS ClientGroupCode,
        D.DateSort,
        FD.QuantitySalesUOM1   AS QtyUOM1,
        FD.NetVolumeCubicMeter AS VolM3
    FROM STBSPOT.olap.FactSalesDaily FD
    JOIN STBSPOT.olap.DimDate          D  ON D.ID  = FD.Date_ID
    JOIN STBSPOT.olap.DimSalesItemData SI ON SI.ID = FD.Item_ID
    JOIN STBSPOT.olap.DimCustomer      C  ON C.ID  = FD.OrderPartner_ID
    INNER JOIN PMap PM
        ON RIGHT(LTRIM(RTRIM(C.CustomerName)), 6) = PM.NumberLast6
    WHERE
        D.DateSort >= @from
        AND D.DateSort <  DATEADD(DAY, 1, (SELECT Today FROM NowD))
),
BaseFut AS (
    SELECT
        CASE
            WHEN LEFT(LTRIM(RTRIM(SI.ProfitCenter)),3) = '190' AND SI.ProductGroup_ID IN (26,27,30,585) THEN '130'
            WHEN LEFT(LTRIM(RTRIM(SI.ProfitCenter)),3) = '190' AND SI.ProductGroup_ID IN (182,183,573)  THEN '160'
            ELSE LEFT(LTRIM(RTRIM(SI.ProfitCenter)),3)
        END AS PC3,
        PM.CompanyNumberStr AS CompanyNumber,
        PM.CompanyNumberInt AS CompanyNumberInt,
        PM.CompanyGroup5    AS ClientGroupCode,
        D.DateSort,
        FV.FutureQuantitySalesUom1 AS QtyUOM1,
        FV.NetVolumeCubicMeter     AS VolM3
    FROM STBSPOT.olap.FactSalesOrderFutureValues FV
    JOIN STBSPOT.olap.DimDate          D  ON D.ID  = FV.Date_ID
    JOIN STBSPOT.olap.DimSalesItemData SI ON SI.ID = FV.Item_ID
    INNER JOIN PMap PM
        ON PM.PartnerID = FV.Partner_ID
    WHERE
        D.DateSort >= (SELECT Today FROM NowD)
),
AllFacts AS (
    SELECT * FROM BaseReal
    UNION ALL
    SELECT * FROM BaseFut
),
R AS (
    SELECT
        CASE WHEN PC3 IN ('170','172') THEN '170' ELSE PC3 END AS CombinedPC,
        CompanyNumber,
        CompanyNumberInt,
        ClientGroupCode,
        DateSort,
        QtyUOM1,
        VolM3
    FROM AllFacts
)
SELECT
    R.CombinedPC       AS ProfitCenter,
    R.CompanyNumber,
    R.ClientGroupCode  AS ClientGroupCode,
    YEAR(R.DateSort)   AS [Year],
    MONTH(R.DateSort)  AS [Month],
    SUM(CASE
        WHEN R.CombinedPC IN ('110','170','171','175') THEN R.VolM3
        ELSE R.QtyUOM1
    END) AS ValorSegunPC
FROM R
WHERE
    R.CombinedPC IN ('110','130','140','141','142','143','144','160','170','171','173','174','175')
    AND R.CompanyNumberInt IS NOT NULL
    AND R.CompanyNumberInt <= 20000
GROUP BY
    R.CombinedPC, R.CompanyNumber, R.ClientGroupCode, YEAR(R.DateSort), MONTH(R.DateSort)
ORDER BY
    ProfitCenter, ClientGroupCode, [Year], [Month];
SQL;

        $rows = DB::connection('sqlsrv_stbspot')->select($sql, [$from]);

        // Accumulate with full precision
        $seen = 0; $missing = 0; $shown = 0;
        $acc = [];

        foreach ($rows as $r) {
            $pc3   = strtoupper(substr(trim((string)$r->ProfitCenter), 0, 3));
            $cg5   = strtoupper(trim((string)$r->ClientGroupCode)); // already 5 chars
            $year  = (int)$r->Year;
            $month = (int)$r->Month;
            $vol   = (float)$r->ValorSegunPC;

            $errs = [];
            if ($cg5 === '' || strlen($cg5) !== 5) $errs[] = 'INVALID_CLIENT_GROUP_CODE';
            if ($pc3 === '' || strlen($pc3) !== 3) $errs[] = 'INVALID_PROFIT_CENTER';
            if ($month < 1 || $month > 12)        $errs[] = 'INVALID_MONTH';
            if (!is_finite($vol))                 $errs[] = 'INVALID_VOLUME';

            $cpcId = $cpcMap["{$cg5}|{$pc3}"] ?? null;
            if (!$cpcId) $errs[] = 'CPC_NOT_FOUND';

            if ($errs) {
                $missing++;
                if ($print && $shown < $printMax) {
                    $this->warn(sprintf(
                        'REJECT ▶ pc=%s cg5=%s y=%d m=%02d vol=%s reason=%s',
                        $pc3, $cg5, $year, $month,
                        is_finite($vol) ? number_format($vol, 6, '.', '') : 'NULL', // print only
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
                    'volume'                  => 0.0, // full precision accumulator
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ];
            }
            $acc[$key]['volume'] += $vol; // NO rounding here
        }

        // Upsert (round ONLY now)
        $upserts = 0;
        if (!$dryRun && $acc) {
            // apply final rounding before writing
            $rowsToWrite = array_map(function(array $r) {
                $r['volume'] = round((float)$r['volume'], $this->roundDecimals);
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
                ['volume','updated_at']
            );
            return count($rows);
        } catch (\Throwable $e) {
            $ok = 0;
            foreach ($rows as $row) {
                try {
                    // re-ensure final value is rounded (idempotent)
                    $row['volume'] = round((float)$row['volume'], $this->roundDecimals);
                    DB::table('sales')->upsert(
                        [$row],
                        ['client_profit_center_id','fiscal_year','month'],
                        ['volume','updated_at']
                    );
                    $ok++;
                } catch (\Throwable $e2) {
                    if ($print && $shown < $printMax) {
                        $this->error(sprintf(
                            'DB_ERROR ▶ cpc_id=%s y=%s m=%s vol=%s err=%s',
                            $row['client_profit_center_id'] ?? 'NULL',
                            $row['fiscal_year'] ?? 'NULL',
                            $row['month'] ?? 'NULL',
                            isset($row['volume']) ? number_format((float)$row['volume'], 6, '.', '') : 'NULL',
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