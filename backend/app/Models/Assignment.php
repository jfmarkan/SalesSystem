<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use SoftDeletes;

    protected $table = 'assignments';

    protected $fillable = [
        'client_profit_center_id',
        'team_id',
        'user_id',
    ];

    public function clientProfitCenter()
    {
        return $this->belongsTo(ClientProfitCenter::class, 'client_profit_center_id', 'id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
