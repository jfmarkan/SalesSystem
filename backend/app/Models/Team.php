<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'manager_user_id',
        'company_id',
    ];

    // Owning company for this team
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // Manager user (nullable)
    public function managerUser()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    // Members through team_members pivot (EXCLUDING manager rows)
    public function members()
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps()
            ->whereNull('team_members.deleted_at')
            ->where(function ($q) {
                // manager should NOT count as "Mitglied" for delete logic
                $q->whereNull('team_members.role')
                  ->orWhere('team_members.role', '!=', 'MANAGER');
            });
    }
}
