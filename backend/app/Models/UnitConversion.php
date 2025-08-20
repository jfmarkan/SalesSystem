<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitConversion extends Model
{
    use SoftDeletes;

    protected $table = 'unit_conversions';

    protected $fillable = [
        'profit_center_code',
        'from_unit',
        'factor_to_m3',
        'factor_to_euro'
    ];

}
