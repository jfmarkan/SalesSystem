<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

trait ImportsErpClients
{
    /** Ajustá si tu conexión se llama distinto */
    protected string $erpConnection = 'sqlsrv_stbspot';

    /** Buffer de errores detallados para el Application Manager */
    protected array $clientSyncErrors = [];

    protected function runClientSync(bool $dryRun = false, bool $print = false, int $printMax = 100): array
    {
        $shown = 0; $seen = 0; $clientUpserts = 0; $assignmentUpserts = 0; $skipped = 0;

        $sql  = $this->getErpClientSyncSql();
        $rows = DB::connection($this->erpConnection)->select($sql);
        $now  = Carbon::now();

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $seen++;

                $cg5   = strtoupper(trim((string)($row->ClientGroupCode ?? '')));
                $cname = trim((string)($row->ClientName ?? ''));
                $gr    = strtolower(trim((string)($row->GroupResponsible ?? ''))); // users.username en minúsculas
                $abc   = trim((string)($row->STB_AbcClassification ?? ''));

                if ($cg5 === '' || strlen($cg5) !== 5) {
                    $skipped++; $this->pushClientSyncError("INVALID_CLIENT_GROUP_CODE ▶ cg5='{$cg5}'");
                    if ($print && $shown < $printMax) { $this->warn("REJECT ▶ {$cg5} reason=INVALID_CLIENT_GROUP_CODE"); $shown++; }
                    continue;
                }

                // --- clasificación (fallback a ID=5 si viene NULL/vacío) ---
                $defaultClassificationId = 5;
                if ($abc === '' || $abc === null) {
                    $classificationId = $defaultClassificationId;
                } else {
                    $classificationId = DB::table('classifications')
                        ->where('classification', $abc)
                        ->value('id');

                    if (!$classificationId) {
                        $this->pushClientSyncError("CLASSIFICATION_NOT_FOUND ▶ cg5='{$cg5}' abc='{$abc}'");
                        if ($print && $shown < $printMax) {
                            $this->warn("WARN ▶ cg5={$cg5} classification '{$abc}' not found -> NULL");
                            $shown++;
                        }
                        // Si querés también caer a 5 cuando no existe el texto, descomentar:
                        // $classificationId = $defaultClassificationId;
                    }
                }

                // Upsert client (siempre actualiza)
                if (!$dryRun) {
                    DB::table('clients')->updateOrInsert(
                        ['client_group_number' => $cg5],
                        ['client_name' => $cname, 'classification_id' => $classificationId, 'updated_at' => $now]
                    );
                    $clientUpserts++;
                }

                // Profit centers del cliente
                $profitCenters = DB::table('client_profit_centers')
                    ->select('id')
                    ->where('client_group_number', $cg5)
                    ->get();

                if ($profitCenters->isEmpty()) {
                    $skipped++; $this->pushClientSyncError("NO_PROFIT_CENTERS ▶ cg5='{$cg5}'");
                    if ($print && $shown < $printMax) { $this->warn("REJECT ▶ cg5={$cg5} reason=NO_PROFIT_CENTERS"); $shown++; }
                    continue;
                }

                // Responsable
                if ($gr === '') {
                    $this->pushClientSyncError("RESPONSIBLE_EMPTY ▶ cg5='{$cg5}'");
                    if ($print && $shown < $printMax) { $this->warn("WARN ▶ cg5={$cg5} responsible empty"); $shown++; }
                    continue;
                }

                // user_id por username lowercase
                $userId = DB::table('users')
                    ->whereRaw('LOWER(username) = ?', [$gr])
                    ->value('id');

                if (!$userId) {
                    $skipped++; $this->pushClientSyncError("USER_NOT_FOUND ▶ cg5='{$cg5}' username='{$gr}'");
                    if ($print && $shown < $printMax) { $this->warn("REJECT ▶ cg5={$cg5} user '{$gr}' not found"); $shown++; }
                    continue;
                }

                // team_id desde team_members (si hay varios, tomamos el primero y avisamos)
                $teamIds = DB::table('team_members')->where('user_id', $userId)->pluck('team_id');
                $teamId  = $teamIds->first();
                if ($teamIds->count() > 1) {
                    $this->pushClientSyncError("MULTIPLE_TEAMS ▶ user_id={$userId} teams=[" . implode(',', $teamIds->all()) . "]");
                    if ($print && $shown < $printMax) { $this->warn("WARN ▶ user_id={$userId} multiple teams, using first={$teamId}"); $shown++; }
                }
                if (!$teamId) {
                    $this->pushClientSyncError("TEAM_NOT_FOUND ▶ user_id={$userId} username='{$gr}'");
                    if ($print && $shown < $printMax) { $this->warn("WARN ▶ user_id={$userId} no team found"); $shown++; }
                }

                // Asignaciones (un solo assignment por CPC: updateOrInsert)
                foreach ($profitCenters as $pc) {
                    if (!$dryRun) {
                        DB::table('assignments')->updateOrInsert(
                            ['client_profit_center_id' => (int)$pc->id],
                            ['user_id' => $userId, 'team_id' => $teamId, 'updated_at' => $now]
                        );
                        $assignmentUpserts++;
                    }
                }
            }

            DB::commit();

            if (!empty($this->clientSyncErrors)) {
                $this->warn("⚠️ Finalizó con advertencias/errores (".count(array_unique($this->clientSyncErrors))." únicos).");
                Log::channel('daily')->error('ERP Client Sync - Issues', $this->toAssocLog($this->clientSyncErrors));
            } else {
                $this->info('✅ Sincronización de clientes finalizada sin errores.');
            }

            return compact('seen','clientUpserts','assignmentUpserts','skipped') + ['shown' => $shown];
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("🛑 Error crítico al sincronizar ERP (Clients): {$e->getMessage()}");
            $this->line("Archivo: {$e->getFile()}"); $this->line("Línea: {$e->getLine()}");
            Log::channel('daily')->error('ERP Client Sync - Critical', [
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return compact('seen','clientUpserts','assignmentUpserts','skipped') + ['shown' => $shown];
        }
    }

    protected function pushClientSyncError(string $message): void
    {
        $this->clientSyncErrors[] = $message;
    }

    protected function toAssocLog(array $messages): array
    {
        $out = []; foreach ($messages as $i => $m) { $out["err_{$i}"] = $m; } return $out;
    }

    /** SQL cruda (trae ClientGroupCode, ClientName, GroupResponsible, STB_AbcClassification) */
    protected function getErpClientSyncSql(): string
    {
        return <<<SQL
DECLARE @from DATE = '2024-04-01';

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
    SELECT PM.CompanyGroup5 AS ClientGroupCode
    FROM STBSPOT.olap.FactSalesDaily FD
    JOIN STBSPOT.olap.DimDate D ON D.ID = FD.Date_ID
    JOIN STBSPOT.olap.DimSalesItemData SI ON SI.ID = FD.Item_ID
    JOIN STBSPOT.olap.DimCustomer C ON C.ID = FD.OrderPartner_ID
    INNER JOIN PMap PM ON RIGHT(LTRIM(RTRIM(C.CustomerName)), 6) = PM.NumberLast6
    WHERE D.DateSort >= @from
    AND D.DateSort < DATEADD(DAY, 1, (SELECT Today FROM NowD))
),
BaseFut AS (
    SELECT PM.CompanyGroup5 AS ClientGroupCode
    FROM STBSPOT.olap.FactSalesOrderFutureValues FV
    JOIN STBSPOT.olap.DimDate D ON D.ID = FV.Date_ID
    JOIN STBSPOT.olap.DimSalesItemData SI ON SI.ID = FV.Item_ID
    INNER JOIN PMap PM ON PM.PartnerID = FV.Partner_ID
    WHERE D.DateSort >= (SELECT Today FROM NowD)
),
AllFacts AS (
    SELECT ClientGroupCode FROM BaseReal
    UNION ALL
    SELECT ClientGroupCode FROM BaseFut
),
ClientGroups AS (
    SELECT DISTINCT ClientGroupCode
    FROM AllFacts
    WHERE ClientGroupCode IS NOT NULL
),
MostFrequentGroupRep AS (
    SELECT
        P1.CompanyNumber AS ClientGroupCode,
        P1.GroupResponsiblePartner_ID,
        ROW_NUMBER() OVER (
            PARTITION BY P1.CompanyNumber
            ORDER BY COUNT(*) DESC
        ) AS rn
    FROM STBSPOT.dbo.Partner P1
    WHERE
        P1.IsCustomer_0004 = 1
        AND P1.CustomerStatus = 1
        AND P1.GroupResponsiblePartner_ID IS NOT NULL
    GROUP BY
        P1.CompanyNumber,
        P1.GroupResponsiblePartner_ID
),
MostFrequentABC AS (
    SELECT
        P1.CompanyNumber AS ClientGroupCode,
        P1.STB_AbcClassification,
        ROW_NUMBER() OVER (
            PARTITION BY P1.CompanyNumber
            ORDER BY COUNT(*) DESC
        ) AS rn
    FROM STBSPOT.dbo.Partner P1
    WHERE
        P1.IsCustomer_0004 = 1
        AND P1.CustomerStatus = 1
        AND P1.STB_AbcClassification IS NOT NULL
    GROUP BY
        P1.CompanyNumber,
        P1.STB_AbcClassification
),
CustomerClassificationMap AS (
    SELECT
        CC.Path,
        CC.Description,
        RIGHT(CC.Path, 5) AS ClientGroupCode,
        ROW_NUMBER() OVER (PARTITION BY RIGHT(CC.Path, 5) ORDER BY LEN(CC.Path) DESC) AS rn
    FROM STBSPOT.dbo.CustomerClassification001 CC
    WHERE CC.Description IS NOT NULL
)
SELECT
    CG.ClientGroupCode,
    CCM.Description AS ClientName,
    P2.Number AS GroupResponsible,
    ABC.STB_AbcClassification
FROM ClientGroups CG
LEFT JOIN MostFrequentGroupRep GR
    ON GR.ClientGroupCode = CG.ClientGroupCode AND GR.rn = 1
LEFT JOIN MostFrequentABC ABC
    ON ABC.ClientGroupCode = CG.ClientGroupCode AND ABC.rn = 1
LEFT JOIN STBSPOT.dbo.Partner P2
    ON P2.ID = GR.GroupResponsiblePartner_ID
LEFT JOIN CustomerClassificationMap CCM
    ON CCM.ClientGroupCode = CG.ClientGroupCode AND CCM.rn = 1
ORDER BY CG.ClientGroupCode;
SQL;
    }
}
