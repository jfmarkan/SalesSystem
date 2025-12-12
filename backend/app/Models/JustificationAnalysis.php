<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustificationAnalysis extends Model
{
    protected $table = 'justifications_analysis';

    protected $fillable = [
        'user_id',
        'pc_code',
        'year',
        'month',
        'type',
        'note',
        'manager_id',
    ];

    protected $casts = [
        'pc_code' => 'integer',
        'user_id' => 'integer',
        'manager_id' => 'integer',
        'year' => 'integer',
        'month' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }


    /* ---------- Accessors ---------- */

    public function getManagerNameAttribute(): string
    {
        $m = $this->manager;
        if (!$m) {
            return '';
        }

        $first = trim((string)($m->first_name ?? ''));
        $last  = trim((string)($m->last_name ?? ''));

        if ($first || $last) {
            return trim($first . ' ' . $last);
        }

        return (string)($m->name ?? '');
    }
}
