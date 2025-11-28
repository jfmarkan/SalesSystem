<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seasonality extends Model
{
    use SoftDeletes;

    protected $table = 'seasonalities';

    protected $fillable = [
        'profit_center_code',
        'fiscal_year',
        'apr',
        'may',
        'jun',
        'jul',
        'aug',
        'sep',
        'oct',
        'nov',
        'dec',
        'jan',
        'feb',
        'mar',
    ];

    protected $casts = [
        'apr' => 'float',
        'may' => 'float',
        'jun' => 'float',
        'jul' => 'float',
        'aug' => 'float',
        'sep' => 'float',
        'oct' => 'float',
        'nov' => 'float',
        'dec' => 'float',
        'jan' => 'float',
        'feb' => 'float',
        'mar' => 'float',
    ];

    public function profitCenters()
    {
        return $this->hasMany(ProfitCenter::class, 'seasonality_id', 'id');
    }
}
