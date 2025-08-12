<?php
// app/Models/TeamMember.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamMember extends Model
{
    use SoftDeletes;

    protected $table = 'team_members';

    public $incrementing = false;
    protected $primaryKey = null; // PK compuesta
    protected $keyType = 'int';

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}