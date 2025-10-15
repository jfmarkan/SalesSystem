<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionItem extends Model
{
    protected $fillable = ['action_plan_id','title','description','due_date','is_completed'];

    protected $casts = [
        'is_completed' => 'boolean',
        'due_date'     => 'date:Y-m-d',
    ];

    public function plan()
    {
        return $this->belongsTo(ActionPlan::class, 'action_plan_id');
    }
}