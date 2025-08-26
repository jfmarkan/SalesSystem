<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraQuotaBudget extends Model
{
    protected $table = 'extra_quota_budgets';

    protected $fillable = [
        'opportunity_group_id','version','month','fiscal_year','volume','calculation_date',
    ];

    protected $casts = [
        'calculation_date' => 'datetime',
    ];
}