<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deviation extends Model
{
    use SoftDeletes;

    protected $table = 'deviations';

    protected $fillable = [
        'profit_center_code',
        'pc_name',
        'client_name',
        'deviation_type',
        'fiscal_year',
        'month',
        'sales',
        'budget',
        'forecast',
        'delta_abs',
        'delta_pct',
        'deviation_ratio',
        'months',
        'sales_series',
        'budget_series',
        'forecast_series',
        'justified',
        'user_id',
    ];

    protected $casts = [
        'sales' => 'float',
        'budget' => 'float',
        'forecast' => 'float',
        'delta_abs' => 'float',
        'delta_pct' => 'float',
        'deviation_ratio' => 'float',
        'months' => 'array',
        'sales_series' => 'array',
        'budget_series' => 'array',
        'forecast_series' => 'array',
        'justified' => 'bool',
    ];

    public function justification()
    {
        return $this->hasOne(Justification::class, 'deviation_id');
    }

    public function actionPlan()
    {
        return $this->hasOne(ActionPlan::class, 'deviation_id');
    }

    public function actionItems()
    {
        return $this->hasManyThrough(
            ActionItem::class,
            ActionPlan::class,
            'deviation_id',
            'action_plan_id',
            'id',
            'id'
        );
    }
}
