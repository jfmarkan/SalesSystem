<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Justification extends Model
{
    protected $table = 'justifications';

    protected $fillable = [
        'deviation_id',
        'user_id',
        'type',
        'comment',
        'plan',
    ];

    public function deviation()
    {
        return $this->belongsTo(Deviation::class, 'deviation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}