<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfitCenter extends Model
{
    use SoftDeletes;

    protected $table = 'profit_centers';

    protected $primaryKey = 'profit_center_code';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'profit_center_code',
        'profit_center_name',
        'seasonality_id',
    ];

    public function seasonality()
    {
        return $this->belongsTo(Seasonality::class, 'seasonality_id', 'id');
    }

    public function clientProfitCenters()
    {
        return $this->hasMany(ClientProfitCenter::class, 'profit_center_code', 'profit_center_code');
    }
}
