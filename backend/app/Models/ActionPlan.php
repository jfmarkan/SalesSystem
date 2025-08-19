<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionPlan extends Model
{
    protected $table = 'action_plans';

    protected $fillable = [
        'deviation_id',
        'user_id',
        'objective',
    ];

    public function deviation()
    {
        return $this->belongsTo(Deviation::class, 'deviation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ActionItem::class, 'action_plan_id');
    }
}
