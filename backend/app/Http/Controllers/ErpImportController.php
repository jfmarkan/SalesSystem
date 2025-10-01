<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Console\Commands\Concerns\ImportsErpSales;
use Illuminate\Database\QueryException;
use Throwable;

class ErpImportController extends Controller
{
    use ImportsErpSales;

    public function auto(Request $request)
    {

            @set_time_limit(0);
    ini_set('max_execution_time', '0');
    ini_set('memory_limit', '2048M');
    ignore_user_abort(true);
    \DB::disableQueryLog();

    try {
        \DB::connection('sqlsrv_stbspot')
            ->getPdo()
            ->setAttribute(\PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 600);
    } catch (\Throwable $e) {
        
    }
        $startedAt = microtime(true);
        $mode = 'auto';

        $lock = Cache::lock('erp:import:lock', 3600);
        if (!$lock->get()) {
            return response()->json([
                'ok'    => false,
                'error' => 'Another import is running',
                'code'  => 'LOCKED',
                'ctx'   => $this->baseCtx($mode, null, false, $startedAt),
            ], 409);
        }

        try {
            $now  = now();
            $from = $now->day <= 4
                ? $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString()
                : $now->copy()->startOfMonth()->toDateString();

            $res = $this->runImport($from, false, $mode, false, 0);

            return response()->json([
                'ok'      => true,
                'mode'    => $mode,
                'from'    => $res['from'],
                'seen'    => (int)$res['seen'],
                'upserts' => (int)$res['upserts'],
                'missing' => (int)$res['missing'],
                'shown'   => (int)$res['shown'],
                'timing_ms' => $this->elapsedMs($startedAt),
            ], 200);
        } catch (Throwable $e) {
            return response()->json($this->errorPayload($e, [
                'mode' => $mode,
                'from' => null,
                'dry'  => false,
                'started_at' => $startedAt,
            ]), 500);
        } finally {
            optional($lock)->release();
        }
    }

    public function manual(Request $request)
    {
        $startedAt = microtime(true);
        $mode = 'manual';

        $fromRaw = (string)$request->input('from', '');
        $dry     = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOL);

        if ($fromRaw === '') {
            return response()->json([
                'ok'    => false,
                'error' => 'Param "from" (YYYY-MM-DD) is required',
                'code'  => 'VALIDATION',
                'ctx'   => $this->baseCtx($mode, null, $dry, $startedAt),
            ], 422);
        }

        try {
            $from = \Carbon\Carbon::parse($fromRaw)->toDateString();
        } catch (Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => 'Invalid "from" date',
                'code'  => 'VALIDATION',
                'detail'=> $e->getMessage(),
                'ctx'   => $this->baseCtx($mode, $fromRaw, $dry, $startedAt),
            ], 422);
        }

        $lock = Cache::lock('erp:import:lock', 3600);
        if (!$lock->get()) {
            return response()->json([
                'ok'    => false,
                'error' => 'Another import is running',
                'code'  => 'LOCKED',
                'ctx'   => $this->baseCtx($mode, $from, $dry, $startedAt),
            ], 409);
        }

        try {
            $res = $this->runImport($from, $dry, $mode, false, 0);

            return response()->json([
                'ok'      => true,
                'mode'    => $mode,
                'from'    => $res['from'],
                'dry_run' => $dry,
                'seen'    => (int)$res['seen'],
                'upserts' => (int)$res['upserts'],
                'missing' => (int)$res['missing'],
                'shown'   => (int)$res['shown'],
                'timing_ms' => $this->elapsedMs($startedAt),
            ], 200);
        } catch (Throwable $e) {
            return response()->json($this->errorPayload($e, [
                'mode' => $mode,
                'from' => $from,
                'dry'  => $dry,
                'started_at' => $startedAt,
            ]), 500);
        } finally {
            optional($lock)->release();
        }
    }

    /* ======================== helpers ======================== */

    /** Build a consistent base context object for responses. */
    private function baseCtx(string $mode, ?string $from, bool $dry, float $startedAt): array
    {
        return [
            'mode'       => $mode,
            'from'       => $from,
            'dry_run'    => $dry,
            'app_env'    => config('app.env'),
            'app_tz'     => config('app.timezone'),
            'php'        => PHP_VERSION,
            'laravel'    => app()->version(),
            'now'        => now()->toDateTimeString(),
            'timing_ms'  => $this->elapsedMs($startedAt),
        ];
    }

    /** Milliseconds since $startedAt. */
    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * Normalize a Throwable into a rich JSON payload.
     * Includes SQLSTATE/driver codes when QueryException/PDOException.
     */
    private function errorPayload(Throwable $e, array $ctx = []): array
    {
        $payload = [
            'ok'       => false,
            'error'    => 'Unhandled exception',
            'code'     => $e->getCode(),
            'exception'=> get_class($e),
            'message'  => $e->getMessage(),
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'trace'    => $this->trimTrace($e->getTrace(), 8),
            'ctx'      => $this->baseCtx($ctx['mode'] ?? 'n/a', $ctx['from'] ?? null, (bool)($ctx['dry'] ?? false), $ctx['started_at'] ?? microtime(true)),
        ];

        // Illuminate DB QueryException → include SQL + bindings + PDO info
        if ($e instanceof QueryException) {
            $payload['sql']       = $e->getSql();
            $payload['bindings']  = $this->stringifyBindings($e->getBindings());
            $payload['sqlstate']  = $e->errorInfo[0] ?? null;
            $payload['driver_code']= $e->errorInfo[1] ?? null;
            $payload['driver_msg']= $e->errorInfo[2] ?? null;
        } else {
            // Generic PDOException (wrapped or previous)
            $pdoInfo = $this->findPdoErrorInfo($e);
            if ($pdoInfo) {
                $payload['sqlstate']   = $pdoInfo[0] ?? null;
                $payload['driver_code']= $pdoInfo[1] ?? null;
                $payload['driver_msg'] = $pdoInfo[2] ?? null;
            }
        }

        // Include previous exception chain (up to 2 levels)
        $prevs = [];
        $p = $e->getPrevious();
        $depth = 0;
        while ($p && $depth < 2) {
            $prevs[] = [
                'exception' => get_class($p),
                'code'      => $p->getCode(),
                'message'   => $p->getMessage(),
                'file'      => $p->getFile(),
                'line'      => $p->getLine(),
            ];
            $p = $p->getPrevious();
            $depth++;
        }
        if ($prevs) $payload['previous'] = $prevs;

        return $payload;
    }

    /** Trim and sanitize stack trace frames (only file/line/class/function). */
    private function trimTrace(array $trace, int $max = 8): array
    {
        $out = [];
        foreach (array_slice($trace, 0, $max) as $f) {
            $out[] = [
                'file'     => $f['file'] ?? null,
                'line'     => $f['line'] ?? null,
                'class'    => $f['class'] ?? null,
                'function' => $f['function'] ?? null,
            ];
        }
        return $out;
    }

    /** Try to extract PDO errorInfo from exception/previous chain. */
    private function findPdoErrorInfo(Throwable $e): ?array
    {
        // Illuminate wraps PDOException; walk the chain
        $cur = $e;
        while ($cur) {
            // Some drivers expose errorInfo via property
            if (property_exists($cur, 'errorInfo') && is_array($cur->errorInfo)) {
                return $cur->errorInfo;
            }
            $cur = $cur->getPrevious();
        }
        return null;
    }

    /** Make bindings printable (avoid huge objects). */
    private function stringifyBindings(array $bindings): array
    {
        return array_map(function ($b) {
            if (is_object($b)) return (string) $b;
            if (is_bool($b))   return $b ? 'true' : 'false';
            if ($b === null)   return 'null';
            return (string) $b;
        }, $bindings);
    }
}