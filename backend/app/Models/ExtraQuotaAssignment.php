<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraQuotaAssignment extends Model
{
    protected $table = 'extra_quota_assignment';

    protected $fillable = [
        'fiscal_year','profit_center_code','user_id','volume','is_published','assignment_date',
    ];

    protected $casts = [
        'is_published'   => 'boolean',
        'assignment_date'=> 'date',
    ];
}
