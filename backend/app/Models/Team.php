<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $table = 'teams';

    protected $fillable = [
        'name',
        'manager_user_id',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id', 'id');
    }

    public function members()
    {
        return $this->hasMany(TeamMember::class, 'team_id', 'id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class, 'team_id', 'id');
    }
}
