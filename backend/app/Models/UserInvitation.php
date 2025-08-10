<?php

// app/Models/UserInvitation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInvitation extends Model
{
    protected $fillable = [
        'email',
        'role',
        'token',
        'expires_at',
    ];
}

