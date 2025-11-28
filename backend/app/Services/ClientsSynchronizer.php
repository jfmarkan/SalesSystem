<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientsSynchronizer
{
    /** conexión MS SQL remota */
    private string $remote = 'sqlsrv_stbspot';

    /** tablas locales */
    private string $clientsTable     = 'clients';
    private string $cpcTable         = 'client_profit_centers';
    private string $assignTable      = 'assignments';
    private string $usersTable       = 'users';
    private string $teamMembersTable = 'team_members';

    /** columnas clients */
    private string $colGroup     = 'client_group_number';
    private string $colName      = 'client_name';
    private string $colClass     = 'classification_id';
    private string $colGroupResp = 'group_responsible'; // nuevo

    /** columnas assignments */
    private string $assignIdCol   = 'id';
    private string $assignCpcCol  = 'client_profit_center_id';
    private string $assignUserCol = 'user_id';
    private string $assignTeamCol = 'team_id';

    /** columnas users */
    private string $usersIdCol       = 'id';
    private string $usersUsernameCol = 'username'; // ajustá si tu esquema usa otro nombre

    /** columnas team_members */
    private string $tmUserCol    = 'user_id';
    private string $tmTeamCol    = 'team_id';
    private ?string $tmPrimaryCol = 'is_primary'; // si no existe, se ignora

    /**
     * options:
     *  - apply: bool         -> true escribe; false dry-run
     *  - verbose: bool       -> log detallado
     *  - log_max_lines: int  -> tope de líneas de log
     *  - max_details: int    -> tope de ítems en arrays de detalle
     *
     * @param array $options
     * @param callable|null $log function(string $msg): void
     * @return array
     */
    public function clientsUpdate(array $options = [], ?callable $log = null): array
    {
        $apply       = (bool)($options['apply'] ?? true);
        $verbose     = array_key_exists('verbose', $options) ? (bool)$options['verbose'] : true;
        $logMaxLines = (int)($options['log_max_lines'] ?? 1500);
        $maxDetails  = (int)($options['max_details'] ?? 500);

        $say = $log ?? static function (string $m): void {};
        $logCount = 0;
        $emit = function (string $m) use ($say, &$logCount, $logMaxLines): void {
            if ($logCount >= $logMaxLines) return;
            $say($m);
            $logCount++;
        };

        // detección de esquema
        if (!Schema::hasColumn($this->usersTable, $this->usersUsernameCol) && Schema::hasColumn($this->usersTable, 'user_name')) {
            $this->usersUsernameCol = 'user_name';
        }
        $hasAssignTeam = Schema::hasColumn($this->assignTable, $this->assignTeamCol);
        $hasGroupResp  = Schema::hasColumn($this->clientsTable, $this->colGroupResp);
        if (!Schema::hasColumn($this->teamMembersTable, $this->tmPrimaryCol)) {
            $this->tmPrimaryCol = null;
        }

        // caches
        $userIdCache = []; // username (lower) => user_id|null
        $teamIdCache = []; // user_id => team_id|null

        $emit('Conectando a SQL Server ['.$this->remote.']');
        /** @var Collection<int, object> $rows */
        $rows = collect(DB::connection($this->remote)->select($this->remoteCte()));
        $emit("Recibidas {$rows->count()} filas");

        $hasCreatedAt = Schema::hasColumn($this->clientsTable, 'created_at');
        $hasUpdatedAt = Schema::hasColumn($this->clientsTable, 'updated_at');
        $now = now();

        $stats = [
            'rows_received' => $rows->count(),

            // clients
            'inserted'  => 0,
            'updated'   => 0,
            'unchanged' => 0,

            // assignments
            'assign_created' => 0,
            'assign_updated' => 0,

            // skips y errores
            'skipped_no_user' => 0,
            'skipped_no_cpc'  => 0,
            'errors'          => 0,

            // detalles
            'inserted_clients'   => [],
            'updated_clients'    => [],   // [{client_group_number,name_old,name_new,class_old,class_new,group_responsible_old,group_responsible_new}]
            'unchanged_clients'  => [],   // [{client_group_number,name,class,group_responsible}]
            'assign_upserts'     => [],   // [{client_group_number,cpc_id,user_old,user_new,team_old,team_new}]
            'assign_create_list' => [],   // [{client_group_number,cpc_id,user_id,team_id}]
            'assign_skipped_no_user' => [], // [{client_group_number,username}]
            'assign_skipped_no_cpc'  => [], // [{client_group_number}]
            'assign_errors'      => [],   // [{client_group_number,error}]
        ];

        if (!$hasAssignTeam) $emit('Aviso: assignments.team_id no existe. Solo se actualizará user_id.');

        $rows->chunk(300)->each(function (Collection $chunk) use (&$stats, $apply, $verbose, $hasCreatedAt, $hasUpdatedAt, $now, $emit, $maxDetails, $hasAssignTeam, $hasGroupResp, &$userIdCache, &$teamIdCache) {
            foreach ($chunk as $r) {
                $cgn = trim((string)($r->ClientArticleNumber ?? ''));
                if ($cgn === '') continue;

                $remoteName = (string)($r->ClientName ?? '');
                $classId    = $this->mapClass($r->STB_AbcClassification ?? null);
                $username   = $this->normalizeUsername($r->GroupResponsible ?? null); // lowercase o null

                try {
                    DB::beginTransaction();
                    if ($verbose) $emit("CGN {$cgn} ► inicio");

                    // === UPSERT CLIENT ===
                    $clientCols = [$this->colName, $this->colClass];
                    if ($hasGroupResp) $clientCols[] = $this->colGroupResp;

                    $client = DB::table($this->clientsTable)
                        ->where($this->colGroup, $cgn)
                        ->first($clientCols);

                    if ($client) {
                        $nameOld  = (string)($client->{$this->colName} ?? '');
                        $classOld = $client->{$this->colClass} ?? null;
                        $grpOld   = $hasGroupResp ? (string)($client->{$this->colGroupResp} ?? '') : null;

                        $update  = [];
                        $changed = false;

                        if ($remoteName !== '' && $remoteName !== $nameOld) {
                            $update[$this->colName] = $remoteName;
                            $changed = true;
                        }
                        if (!is_null($classId) && (int)$classId !== (int)$classOld) {
                            $update[$this->colClass] = $classId;
                            $changed = true;
                        }
                        if ($hasGroupResp && $username !== null && $username !== $grpOld) {
                            $update[$this->colGroupResp] = $username; // guardar lowercase
                            $changed = true;
                        }

                        if ($changed) {
                            if ($hasUpdatedAt) $update['updated_at'] = $now;
                            if ($apply) {
                                DB::table($this->clientsTable)->where($this->colGroup, $cgn)->update($update);
                            }
                            $stats['updated']++;
                            if (count($stats['updated_clients']) < $maxDetails) {
                                $stats['updated_clients'][] = [
                                    'client_group_number'    => $cgn,
                                    'name_old'               => $nameOld,
                                    'name_new'               => $update[$this->colName] ?? $nameOld,
                                    'class_old'              => $classOld,
                                    'class_new'              => $update[$this->colClass] ?? $classOld,
                                    'group_responsible_old'  => $hasGroupResp ? $grpOld : null,
                                    'group_responsible_new'  => $hasGroupResp ? ($update[$this->colGroupResp] ?? $grpOld) : null,
                                ];
                            }
                            if ($verbose) $emit("CGN {$cgn} • client UPDATE");
                        } else {
                            $stats['unchanged']++;
                            if (count($stats['unchanged_clients']) < $maxDetails) {
                                $stats['unchanged_clients'][] = [
                                    'client_group_number' => $cgn,
                                    'name'                => $nameOld,
                                    'class'               => $classOld,
                                    'group_responsible'   => $hasGroupResp ? $grpOld : null,
                                ];
                            }
                            if ($verbose) $emit("CGN {$cgn} • client UNCHANGED");
                        }
                    } else {
                        $insert = [
                            $this->colGroup => $cgn,
                            $this->colName  => $remoteName,
                            $this->colClass => $classId,
                        ];
                        if ($hasGroupResp) $insert[$this->colGroupResp] = $username; // puede ser null
                        if ($hasCreatedAt) $insert['created_at'] = $now;
                        if ($hasUpdatedAt) $insert['updated_at'] = $now;

                        if ($apply) DB::table($this->clientsTable)->insert($insert);
                        $stats['inserted']++;
                        if (count($stats['inserted_clients']) < $maxDetails) {
                            $stats['inserted_clients'][] = $cgn;
                        }
                        if ($verbose) $emit("CGN {$cgn} • client INSERT");
                    }

                    // === USER y TEAM para assignments ===
                    if (!$username) {
                        $stats['skipped_no_user']++;
                        if (count($stats['assign_skipped_no_user']) < $maxDetails) {
                            $stats['assign_skipped_no_user'][] = ['client_group_number' => $cgn, 'username' => null];
                        }
                        if ($verbose) $emit("CGN {$cgn} • SKIP assignments: sin GroupResponsible");
                        DB::commit();
                        continue;
                    }

                    // lookup case-insensitive
                    $userId = array_key_exists($username, $userIdCache) ? $userIdCache[$username] : null;
                    if ($userId === null) {
                        $col = $this->usersUsernameCol;
                        $u = DB::table($this->usersTable)
                            ->whereRaw('LOWER('.$col.') = ?', [$username])
                            ->value($this->usersIdCol);
                        $userId = $u ? (int)$u : null;
                        $userIdCache[$username] = $userId;
                    }
                    if ($verbose) $emit("CGN {$cgn} • user: '{$username}' → id=".($userId ?? 'NULL'));

                    if ($userId === null) {
                        $stats['skipped_no_user']++;
                        if (count($stats['assign_skipped_no_user']) < $maxDetails) {
                            $stats['assign_skipped_no_user'][] = ['client_group_number' => $cgn, 'username' => $username];
                        }
                        if ($verbose) $emit("CGN {$cgn} • SKIP assignments: username no existe");
                        DB::commit();
                        continue;
                    }

                    $teamId = array_key_exists($userId, $teamIdCache) ? $teamIdCache[$userId] : null;
                    if (!array_key_exists($userId, $teamIdCache)) {
                        $q = DB::table($this->teamMembersTable)->where($this->tmUserCol, $userId);
                        if ($this->tmPrimaryCol) $q->orderByDesc($this->tmPrimaryCol);
                        $q->orderBy($this->tmTeamCol, 'asc');
                        $tm = $q->value($this->tmTeamCol);
                        $teamId = $tm ? (int)$tm : null;
                        $teamIdCache[$userId] = $teamId;
                    }
                    if ($verbose) $emit("CGN {$cgn} • team_id: ".($teamId ?? 'NULL').($hasAssignTeam ? '' : ' (omitido, columna no existe)'));

                    // CPCs del cliente
                    $cpcIds = DB::table($this->cpcTable)
                        ->where($this->colGroup, $cgn)
                        ->pluck('id')->all();

                    if (!$cpcIds) {
                        $stats['skipped_no_cpc']++;
                        if (count($stats['assign_skipped_no_cpc']) < $maxDetails) {
                            $stats['assign_skipped_no_cpc'][] = ['client_group_number' => $cgn];
                        }
                        if ($verbose) $emit("CGN {$cgn} • SKIP assignments: sin CPCs");
                        DB::commit();
                        continue;
                    }
                    if ($verbose) $emit("CGN {$cgn} • CPCs: ".implode(',', $cpcIds));

                    // Assignments por CPC
                    foreach ($cpcIds as $cpcId) {
                        $cols = [$this->assignIdCol, $this->assignUserCol];
                        if ($hasAssignTeam) $cols[] = $this->assignTeamCol;

                        $rows = DB::table($this->assignTable)
                            ->where($this->assignCpcCol, $cpcId)
                            ->get($cols);

                        if ($rows->isEmpty()) {
                            $payload = [
                                $this->assignCpcCol  => $cpcId,
                                $this->assignUserCol => $userId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                            if ($hasAssignTeam) $payload[$this->assignTeamCol] = $teamId;

                            if ($apply) DB::table($this->assignTable)->insert($payload);
                            $stats['assign_created']++;

                            if (count($stats['assign_create_list']) < $maxDetails) {
                                $stats['assign_create_list'][] = [
                                    'client_group_number' => $cgn,
                                    'cpc_id'  => $cpcId,
                                    'user_id' => $userId,
                                    'team_id' => $hasAssignTeam ? $teamId : null,
                                ];
                            }
                            if ($verbose) $emit("CGN {$cgn} • CPC {$cpcId} assignment CREATE");
                            continue;
                        }

                        foreach ($rows as $row) {
                            $oldUser = (int)($row->{$this->assignUserCol} ?? 0);
                            $oldTeam = $hasAssignTeam ? ($row->{$this->assignTeamCol} ?? null) : null;

                            $upd = [];
                            $changed = false;

                            if ($oldUser !== $userId) { $upd[$this->assignUserCol] = $userId; $changed = true; }
                            if ($hasAssignTeam && $oldTeam !== $teamId) { $upd[$this->assignTeamCol] = $teamId; $changed = true; }

                            if ($changed) {
                                $upd['updated_at'] = $now;
                                if ($apply) {
                                    DB::table($this->assignTable)
                                        ->where($this->assignIdCol, $row->{$this->assignIdCol})
                                        ->update($upd);
                                }
                                $stats['assign_updated']++;

                                if (count($stats['assign_upserts']) < $maxDetails) {
                                    $stats['assign_upserts'][] = [
                                        'client_group_number' => $cgn,
                                        'cpc_id'   => $cpcId,
                                        'user_old' => $oldUser ?: null,
                                        'user_new' => $upd[$this->assignUserCol] ?? $oldUser,
                                        'team_old' => $oldTeam,
                                        'team_new' => $hasAssignTeam ? ($upd[$this->assignTeamCol] ?? $oldTeam) : null,
                                    ];
                                }
                                if ($verbose) $emit("CGN {$cgn} • CPC {$cpcId} assignment UPDATE");
                            } else {
                                if ($verbose) $emit("CGN {$cgn} • CPC {$cpcId} assignment UNCHANGED");
                            }
                        }
                    }

                    if ($verbose) $emit("CGN {$cgn} ► fin");
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $stats['errors']++;
                    $emit("CGN {$cgn} • ERROR: ".$e->getMessage());
                    if (count($stats['assign_errors']) < $maxDetails) {
                        $stats['assign_errors'][] = [
                            'client_group_number' => $cgn,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
        });

        $emit("Resumen: clients ins={$stats['inserted']} upd={$stats['updated']} same={$stats['unchanged']} | assignments ins={$stats['assign_created']} upd={$stats['assign_updated']} | skips user={$stats['skipped_no_user']} cpc={$stats['skipped_no_cpc']} | errors={$stats['errors']}");

        return ['ok' => true, 'summary' => $stats];
    }

    /** username → lowercase o null */
    private function normalizeUsername($u): ?string
    {
        if ($u === null) return null;
        $v = trim((string)$u);
        if ($v === '') return null;
        return mb_strtolower($v, 'UTF-8');
    }

    /** A,B,C,D,X,PA,PB -> 1..7 */
    private function mapClass($raw): ?int
    {
        if ($raw === null) return null;
        $v = strtoupper((string)$raw);
        $v = str_replace([' ', ',', ';', '-', '_'], '', $v);
        return ['A'=>1,'B'=>2,'C'=>3,'D'=>4,'X'=>5,'PA'=>6,'PB'=>7][$v] ?? null;
    }

    /** CTE SQL Server */
    private function remoteCte(): string
    {
        return <<<SQL
;WITH PartnerBase AS (
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
    PB.ClientArticleNumber
SQL;
    }
}
