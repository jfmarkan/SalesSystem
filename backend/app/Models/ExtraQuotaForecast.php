<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraQuotaForecast extends Model
{
    protected $table = 'extra_quota_forecasts';

    protected $fillable = [
        'opportunity_group_id','version','month','fiscal_year','volume','created_by',
    ];
}
