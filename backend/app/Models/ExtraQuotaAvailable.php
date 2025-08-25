<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraQuotaAvailable extends Model
{
    protected $table = 'extra_quota_available';

    protected $fillable = [
        'fiscal_year', 'profit_center_code', 'volume',
    ];
}