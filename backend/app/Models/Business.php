<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    

    public function owner() {
        return $this->belongsTo(User::class, 'owner_id');
    }
    
    public function team() {
        return $this->hasMany(Team::class);
    }
}
