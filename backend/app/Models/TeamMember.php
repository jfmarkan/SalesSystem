<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamMember extends Model
{
    use SoftDeletes;

    protected $table = 'team_members';

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
    ];

    // Team of the member
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    // User that belongs to the team
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
