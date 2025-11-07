<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientsSynchronizer
{
    private string $remote = 'sqlsrv_stbspot'; // conexión MS SQL

    // tabla y columnas locales
    private string $clientsTable = 'clients';
    private string $colGroup = 'client_group_number';
    private string $colName = 'client_name';
    private string $colClass = 'classification_id';

    /**
     * Único punto de entrada. Se ampliará con más pasos.
     * Paso 1: crear faltantes en `clients`.
     */
    public function clientsUpdate(array $options = []): array
    {
        /** @var Collection<int, object> $rows */
        $rows = collect(DB::connection($this->remote)->select($this->remoteCte()));

        $hasCreatedAt = Schema::hasColumn($this->clientsTable, 'created_at');
        $hasUpdatedAt = Schema::hasColumn($this->clientsTable, 'updated_at');
        $now = now();

        $stats = [
            'rows_received'     => $rows->count(),
            'existing_skipped'  => 0,
            'to_create'         => 0,
            'created'           => 0,
            'created_keys'      => [],
        ];

        $rows->chunk(500)->each(function (Collection $chunk) use (&$stats, $hasCreatedAt, $hasUpdatedAt, $now) {
            $batch = [];

            foreach ($chunk as $r) {
                $article = (string)($r->ClientArticleNumber ?? '');
                if ($article === '') {
                    continue;
                }

                $exists = DB::table($this->clientsTable)
                    ->where($this->colGroup, $article)
                    ->exists();

                if ($exists) {
                    $stats['existing_skipped']++;
                    continue;
                }

                $classId = $this->mapClass($r->STB_AbcClassification ?? null);

                $record = [
                    $this->colGroup => $article,
                    $this->colName  => (string)($r->ClientName ?? ''),
                    $this->colClass => $classId,
                ];
                if ($hasCreatedAt) { $record['created_at'] = $now; }
                if ($hasUpdatedAt) { $record['updated_at'] = $now; }

                $batch[] = $record;
                $stats['to_create']++;
                $stats['created_keys'][] = $article;
            }

            if (!empty($batch)) {
                $stats['created'] += DB::table($this->clientsTable)->insertOrIgnore($batch);
            }
        });

        return [
            'ok' => true,
            'step' => 'seed-missing-clients',
            'summary' => $stats,
        ];
    }

    private function mapClass($raw): ?int
    {
        if ($raw === null) return null;
        $v = strtoupper((string)$raw);
        $v = str_replace([' ', ',', ';', '-', '_'], '', $v); // "P, A" -> "PA"
        $map = [
            'A'  => 1,
            'B'  => 2,
            'C'  => 3,
            'D'  => 4,
            'X'  => 5,
            'PA' => 6,
            'PB' => 7,
        ];
        return $map[$v] ?? null;
    }

    private function remoteCte(): string
    {
        return <<<SQL
WITH PartnerBase AS (
    SELECT
        CC.Code AS ClientArticleNumber,
        CC.Description AS ClientName,
        P.GroupResponsiblePartner_ID,
        P.STB_AbcClassification
    FROM STBSPOT.dbo.Partner AS P
    LEFT JOIN STBSPOT.dbo.CustomerClassification001 AS CC
        ON CC.ID = P.CustomerClassification001_ID
    WHERE
        P.IsCustomer_0004 = 1
        AND P.CustomerStatus = 1
        AND TRY_CONVERT(INT, CC.Code) BETWEEN 10000 AND 19999
),
MostFrequentResponsible AS (
    SELECT
        ClientArticleNumber,
        GroupResponsiblePartner_ID,
        COUNT(*) AS Cnt,
        ROW_NUMBER() OVER (PARTITION BY ClientArticleNumber ORDER BY COUNT(*) DESC) AS rn
    FROM PartnerBase
    WHERE GroupResponsiblePartner_ID IS NOT NULL
    GROUP BY ClientArticleNumber, GroupResponsiblePartner_ID
),
MostFrequentABC AS (
    SELECT
        ClientArticleNumber,
        STB_AbcClassification,
        COUNT(*) AS Cnt,
        ROW_NUMBER() OVER (PARTITION BY ClientArticleNumber ORDER BY COUNT(*) DESC) AS rn
    FROM PartnerBase
    WHERE STB_AbcClassification IS NOT NULL
    GROUP BY ClientArticleNumber, STB_AbcClassification
)
SELECT
    PB.ClientArticleNumber,
    PB.ClientName,
    GP.Number AS GroupResponsible,
    ABC.STB_AbcClassification
FROM PartnerBase PB
LEFT JOIN MostFrequentResponsible MR
    ON MR.ClientArticleNumber = PB.ClientArticleNumber AND MR.rn = 1
LEFT JOIN STBSPOT.dbo.Partner GP
    ON GP.ID = MR.GroupResponsiblePartner_ID
LEFT JOIN MostFrequentABC ABC
    ON ABC.ClientArticleNumber = PB.ClientArticleNumber AND ABC.rn = 1
GROUP BY
    PB.ClientArticleNumber, PB.ClientName, GP.Number, ABC.STB_AbcClassification
ORDER BY
    PB.ClientArticleNumber;
SQL;
    }
}
