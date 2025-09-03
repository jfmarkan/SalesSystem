<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesforceController extends Controller
{
    /* ---------- helpers ---------- */

    private function fyStartYear(Request $req): int {
        $raw = $req->query('fiscal_year', $req->query('fy', date('Y')));
        return (int) preg_replace('/\D+/', '', (string)$raw);
    }

    private function currentFYStart(): int {
        $now = now();
        return ($now->month >= 4) ? (int)$now->year : (int)$now->year - 1; // Abril=4
    }

    private function fyYtdIndex(int $fyStart): int {
        // Índice fiscal: abr=1..mar=12
        $mapCalToFiscal = [1=>10,2=>11,3=>12,4=>1,5=>2,6=>3,7=>4,8=>5,9=>6,10=>7,11=>8,12=>9];
        $curFYStart = $this->currentFYStart();
        if ($fyStart < $curFYStart) return 12; // FY pasado: todo el año
        if ($fyStart > $curFYStart) return 0;  // FY futuro: 0
        $m = (int)date('n'); // mes actual
        return $mapCalToFiscal[$m] ?? 0;
    }

    private function hasConv(): bool {
        return Schema::hasTable('unit_conversions');
    }

    private function convFactorExpr(string $alias = 'uc'): string {
        foreach (['factor_to_m3', 'factos_to_m3', 'to_m3_factor', 'factor_m3'] as $c) {
            if (Schema::hasTable('unit_conversions') && Schema::hasColumn('unit_conversions', $c)) {
                return "COALESCE($alias.$c,1)";
            }
        }
        return "1";
    }

    public function index(Request $request)
    {
        $hasRoe  = Schema::hasColumn('users', 'roe_id');
        $hasRole = Schema::hasColumn('users', 'role_id');

        $q = User::query()
            ->select('users.id','users.first_name','users.last_name')
            ->with(['details:id,user_id,profile_picture'])
            ->orderBy('users.first_name')
            ->orderBy('users.last_name');

        // Filtra: solo roles >= 3 (manager, reps)
        if ($hasRoe && $hasRole) {
            $q->where(function($qq){
                $qq->where('users.roe_id', '>=', 3)
                   ->orWhere('users.role_id', '>=', 3);
            });
        } elseif ($hasRoe) {
            $q->where('users.roe_id', '>=', 3);
        } elseif ($hasRole) {
            $q->where('users.role_id', '>=', 3);
        } else {
            // si no existe ninguna columna, no devolvemos nada
            return response()->json([]);
        }

        $users = $q->get()->map(function($u){
            return [
                'id'               => (int)$u->id,
                'first_name'       => (string)($u->first_name ?? ''),
                'last_name'        => (string)($u->last_name ?? ''),
                'name'             => trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                'profile_picture'  => optional($u->details)->profile_picture,
            ];
        });

        return response()->json($users->values());
    }

    // GET /api/salesforce/summary?fiscal_year=YYYY
    public function summary(Request $req)
{
    $auth    = $req->user();
    $fyStart = $this->fyStartYear($req);
    $ytdIdx  = $this->fyYtdIndex($fyStart);

    // ¿manager o superior? (con Spatie: tiene algún rol con id>=3)
    // Si NO es manager, solo ve su propio usuario.
    $isMgr = true; // por si usás gates: ajustá si querés
    // si preferís chequear por permiso/rol, dejá isMgr=true y filtramos por roles abajo

    $hasUserDetails = Schema::hasTable('user_details') && Schema::hasColumn('user_details','avatar_url');

    // Subconsulta de usuarios visibles (id, nombre completo, avatar)
    $usersForJoinQ = DB::table('users as u')
        ->leftJoin('model_has_roles as mr', function($j){
            $j->on('mr.model_id','=','u.id')
              ->where('mr.model_type','=', \App\Models\User::class);
        })
        ->leftJoin('roles as r', 'r.id', '=', 'mr.role_id')
        ->when($hasUserDetails, fn($q)=>$q->leftJoin('user_details as ud','ud.user_id','=','u.id'))
        ->selectRaw("
            u.id,
            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS name
            ".($hasUserDetails ? ", ud.avatar_url" : ", NULL AS avatar_url")."
        ")
        ->groupBy('u.id','u.first_name','u.last_name', ...($hasUserDetails ? ['ud.avatar_url'] : []))
        ->when(!$isMgr, fn($q)=>$q->where('u.id', $auth->id))                  // rep: solo él
        ->when($isMgr,  fn($q)=>$q->where('r.id','>=',3));                     // manager: roles.id >= 3

    // Lista de IDs (para métricas)
    $users = $usersForJoinQ->get();
    if ($users->isEmpty()) return response()->json([]);
    $userIds = $users->pluck('id')->all();

    // Factor a m³
    $factor = '1';
    if ($this->hasConv()) $factor = $this->convFactorExpr('uc');

    /* Asignado extra m³ por usuario (FY) */
    $assignQ = DB::table('extra_quota_assignments as a')
        ->whereIn('a.user_id', $userIds)
        ->where('a.is_published', true)
        ->where('a.fiscal_year', $fyStart);
    if ($this->hasConv()) $assignQ->leftJoin('unit_conversions as uc','uc.profit_center_code','=','a.profit_center_code');

    $assignSub = $assignQ
        ->selectRaw('a.user_id, COALESCE(SUM(CAST(a.volume AS DECIMAL(32,8)) * ('.$factor.')),0) AS extra_assigned_m3')
        ->groupBy('a.user_id');

    /* Última versión por grupo (FY) */
    $lv = DB::table('sales_opportunities')
        ->select('user_id','opportunity_group_id', DB::raw('MAX(version) AS max_version'))
        ->whereIn('user_id', $userIds)
        ->where('fiscal_year', $fyStart)
        ->groupBy('user_id','opportunity_group_id');

    /* Oportunidades WON + OPEN ponderado */
    $opsQ = DB::table('sales_opportunities as s')
        ->joinSub($lv, 'lv', function($j){
            $j->on('lv.user_id','=','s.user_id')
              ->on('lv.opportunity_group_id','=','s.opportunity_group_id')
              ->on('lv.max_version','=','s.version');
        })
        ->whereIn('s.user_id', $userIds);
    if ($this->hasConv()) $opsQ->leftJoin('unit_conversions as uc','uc.profit_center_code','=','s.profit_center_code');

    $opsSub = $opsQ->selectRaw("
            s.user_id,
            COALESCE(SUM(CASE WHEN s.status='won'
                THEN CAST(s.volume AS DECIMAL(32,8)) * ($factor)
                ELSE 0 END),0) AS extra_converted_m3,
            COALESCE(SUM(CASE WHEN s.status='open'
                THEN (CAST(s.volume AS DECIMAL(32,8)) * ($factor)) * (LEAST(GREATEST(s.probability_pct,0),100)/100.0)
                ELSE 0 END),0) AS extra_open_weighted_m3
        ")
        ->groupBy('s.user_id');

    /* Forecast YTD m³ (últimas versiones) */
    $fcSub = DB::table('sales_opportunities as s')
        ->joinSub($lv, 'lv', function($j){
            $j->on('lv.user_id','=','s.user_id')
              ->on('lv.opportunity_group_id','=','s.opportunity_group_id')
              ->on('lv.max_version','=','s.version');
        })
        ->join('extra_quota_forecasts as f', function($j){
            $j->on('f.opportunity_group_id','=','s.opportunity_group_id')
              ->on('f.version','=','s.version');
        })
        ->whereIn('s.user_id', $userIds)
        ->where('f.fiscal_year', $fyStart)
        ->when($ytdIdx > 0, fn($q)=>$q->where('f.month','<=',$ytdIdx), fn($q)=>$q->whereRaw('1=0'));
    if ($this->hasConv()) $fcSub->leftJoin('unit_conversions as uc','uc.profit_center_code','=','s.profit_center_code');

    $fcSub = $fcSub->selectRaw("
            s.user_id,
            COALESCE(SUM(CAST(f.volume AS DECIMAL(32,8)) * ($factor)),0) AS forecast_ytd_m3
        ")
        ->groupBy('s.user_id');

    /* Clientes por usuario */
    $clientsSub = DB::table('assignments')
        ->whereIn('user_id', $userIds)
        ->selectRaw('user_id, COUNT(DISTINCT client_profit_center_id) AS clients_count')
        ->groupBy('user_id');

    // Repetimos la subconsulta de usuarios para el join final (misma lógica de visibilidad)
    $usersSubForJoin = DB::table('users as u')
        ->leftJoin('model_has_roles as mr', function($j){
            $j->on('mr.model_id','=','u.id')
              ->where('mr.model_type','=', \App\Models\User::class);
        })
        ->leftJoin('roles as r', 'r.id', '=', 'mr.role_id')
        ->when($hasUserDetails, fn($q)=>$q->leftJoin('user_details as ud','ud.user_id','=','u.id'))
        ->selectRaw("
            u.id,
            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS name
            ".($hasUserDetails ? ", ud.avatar_url" : ", NULL AS avatar_url")."
        ")
        ->groupBy('u.id','u.first_name','u.last_name', ...($hasUserDetails ? ['ud.avatar_url'] : []))
        ->when(!$isMgr, fn($q)=>$q->where('u.id', $auth->id))
        ->when($isMgr,  fn($q)=>$q->where('r.id','>=',3));

    /* Join final por usuario */
    $rows = DB::query()
        ->fromSub($usersSubForJoin, 'u')
        ->leftJoinSub($assignSub,  'a', 'a.user_id', '=', 'u.id')
        ->leftJoinSub($opsSub,     'o', 'o.user_id', '=', 'u.id')
        ->leftJoinSub($fcSub,      'f', 'f.user_id', '=', 'u.id')
        ->leftJoinSub($clientsSub, 'c', 'c.user_id', '=', 'u.id')
        ->selectRaw("
            u.id, u.name, u.avatar_url,
            COALESCE(c.clients_count,0) AS clients_count,
            COALESCE(a.extra_assigned_m3,0)      AS extra_assigned_m3,
            COALESCE(o.extra_converted_m3,0)     AS extra_converted_m3,
            COALESCE(o.extra_open_weighted_m3,0) AS extra_open_weighted_m3,
            COALESCE(f.forecast_ytd_m3,0)        AS forecast_ytd_m3
        ")
        ->orderBy('u.name')
        ->get();

    // Accuracy (0..100)
        $out = $rows->map(function($r){
            $won   = (float)$r->extra_converted_m3;
            $fcYtd = (float)$r->forecast_ytd_m3;
            $den   = max(1.0, $fcYtd);
            $acc   = 100.0 - (abs($fcYtd - $won) * 100.0 / $den);
            $acc   = max(0.0, min(100.0, $acc));
            return [
                'id'                     => (int)$r->id,
                'name'                   => $r->name,
                'avatar_url'             => $r->avatar_url,
                'clients_count'          => (int)$r->clients_count,
                'forecast_accuracy_pct'  => (int)round($acc),
                'extra_assigned_m3'      => (float)$r->extra_assigned_m3,
                'extra_converted_m3'     => (float)$r->extra_converted_m3,
                'extra_open_weighted_m3' => (float)$r->extra_open_weighted_m3,
                'forecast_ytd_m3'        => (float)$r->forecast_ytd_m3,
            ];
        });

        return response()->json($out->values());
    }

}