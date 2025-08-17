<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Budget extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_profit_center_id',
        'fiscal_year',
        'month',
        'amount',
    ];

    protected $casts = [
        'client_profit_center_id' => 'int',
        'fiscal_year' => 'int',
        'month' => 'int',
        'amount' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function cpc()
    {
        return $this->belongsTo(ClientProfitCenter::class, 'client_profit_center_id');
    }

    public function scopeOfPeriod(Builder $q, int $fy, ?int $month = null): Builder
    {
        $q->where('fiscal_year', $fy);
        if ($month !== null) $q->where('month', $month);
        return $q;
    }
}
