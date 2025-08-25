<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOpportunity extends Model
{
    protected $table = 'sales_opportunities';

    protected $fillable = [
        'user_id','fiscal_year','profit_center_code','volume','probability_pct',
        'estimated_start_date','comments','status',
        'opportunity_group_id','version',
        'potential_client_name','is_won','won_at','is_lost','lost_at','client_group_number',
    ];

    protected $casts = [
        'is_won' => 'boolean',
        'is_lost'=> 'boolean',
        'estimated_start_date' => 'date',
        'won_at' => 'datetime',
        'lost_at'=> 'datetime',
    ];
}
