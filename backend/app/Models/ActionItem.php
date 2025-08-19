<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionItem extends Model
{
    protected $table = 'action_items';

    protected $fillable = [
        'action_plan_id',
        'title',
        'description',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function plan()
    {
        return $this->belongsTo(ActionPlan::class, 'action_plan_id');
    }
}