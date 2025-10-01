<?php
// app/Http/Controllers/DeviationDetectController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;
use Throwable;

class DeviationDetectController extends Controller
{
    public function detect(Request $request)
    {
        $started = microtime(true);
        $userId  = $request->input('user_id'); // optional

        $lock = Cache::lock('deviations:detect:lock', 7200);
        if (!$lock->get()) {
            return response()->json([
                'ok'=>false,'error'=>'Another deviations run is in progress','code'=>'LOCKED',
                'timing_ms'=>$this->ms($started),
            ], 409);
        }

        try {
            $args = [];
            if ($userId !== null && $userId !== '') {
                if (!ctype_digit((string)$userId)) {
                    return response()->json(['ok'=>false,'error'=>'user_id must be numeric','code'=>'VALIDATION'], 422);
                }
                $args['--user_id'] = (int)$userId;
            }

            Artisan::call('deviations:detect', $args);
            $output = Artisan::output();

            return response()->json([
                'ok'=>true,
                'args'=>$args,
                'output'=>$output,
                'timing_ms'=>$this->ms($started),
            ]);
        } catch (Throwable $e) {
            return response()->json($this->err($e, $started), 500);
        } finally {
            optional($lock)->release();
        }
    }

    private function ms(float $t): int { return (int) round((microtime(true)-$t)*1000); }

    private function err(Throwable $e, float $started): array
    {
        $p = [
            'ok'=>false,'error'=>'Unhandled exception','exception'=>get_class($e),
            'code'=>$e->getCode(),'message'=>$e->getMessage(),
            'file'=>$e->getFile(),'line'=>$e->getLine(),'timing_ms'=>$this->ms($started),
        ];
        if ($e instanceof QueryException) {
            $p['sql'] = $e->getSql();
            $p['bindings'] = $e->getBindings();
            $p['sqlstate'] = $e->errorInfo[0] ?? null;
            $p['driver_code'] = $e->errorInfo[1] ?? null;
            $p['driver_msg'] = $e->errorInfo[2] ?? null;
        }
        return $p;
    }
}