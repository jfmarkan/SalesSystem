<?php
// app/Models/BudgetCase.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetCase extends Model
{
    protected $table = 'budget_cases';

    protected $fillable = [
        'client_profit_center_id',
        'fiscal_year',
        'best_case',
        'worst_case',
        'skip_budget',
    ];

    protected $casts = [
        'client_profit_center_id' => 'int',
        'fiscal_year' => 'int',
        'best_case' => 'float',
        'worst_case' => 'float',
        'skip_budget' => 'bool',
    ];

    public function cpc(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ClientProfitCenter::class, 'client_profit_center_id');
    }
}
