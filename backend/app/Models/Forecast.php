<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Forecast extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_profit_center_id',
        'fiscal_year',
        'month',
        'amount',
        'version',
        'user_id',
    ];

    protected $casts = [
        'client_profit_center_id' => 'int',
        'fiscal_year' => 'int',
        'month' => 'int',
        'amount' => 'int',
        'version' => 'int',
        'user_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function cpc()
    {
        return $this->belongsTo(ClientProfitCenter::class, 'client_profit_center_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfPeriod(Builder $q, int $fy, ?int $month = null): Builder
    {
        $q->where('fiscal_year', $fy);
        if ($month !== null) $q->where('month', $month);
        return $q;
    }

    // Última versión por CPC/FY/Mes usando ventana (MySQL 8+/PostgreSQL)
    public function scopeLatestWindow(Builder $q): Builder
    {
        $q->fromSub("
            select *, row_number() over (
                partition by client_profit_center_id, fiscal_year, month
                order by version desc, id desc
            ) as rn
            from forecasts
            where deleted_at is null
        ", 'fx')->where('rn', 1);
        return $q;
    }

    // Alternativa sin ventanas: join a max(version)
    public function scopeLatestByJoin(Builder $q): Builder
    {
        $q->joinSub(
            DB::table('forecasts')
                ->select('client_profit_center_id','fiscal_year','month', DB::raw('MAX(version) as maxv'))
                ->whereNull('deleted_at')
                ->groupBy('client_profit_center_id','fiscal_year','month'),
            'mx',
            function ($j) {
                $j->on('forecasts.client_profit_center_id', '=', 'mx.client_profit_center_id')
                    ->on('forecasts.fiscal_year', '=', 'mx.fiscal_year')
                    ->on('forecasts.month', '=', 'mx.month')
                    ->on('forecasts.version', '=', 'mx.maxv');
            }
        )->whereNull('forecasts.deleted_at');
        return $q->select('forecasts.*');
    }
}
