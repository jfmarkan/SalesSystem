<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deviation extends Model
{
    use SoftDeletes;

    protected $table = 'deviations';

    protected $fillable = [
        'client_profit_center_id',
        'deviation_type',
        'fiscal_year',
        'month',
        'deviation_ratio',
        'explanation',
        'user_id',
    ];

    protected $casts = [
        'percent' => 'float',
    ];

    public function profitCenter()
    {
        return $this->belongsTo(ProfitCenter::class, 'profit_center_code', 'profit_center_code');
    }
}
