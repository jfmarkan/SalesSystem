<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, HasFactory, Notifiable, HasApiTokens, LogsActivity;

    protected $fillable = [
        'first_name',
        'last_name',
        'role_id',
        'username',
        'email',
        'password',
        'otp',
        'otp_expires_at',
    ];

    protected static $logAttributes = ['first_name', 'last_name', 'email'];
    protected static $logName = 'user';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(self::$logAttributes)
            ->useLogName(self::$logName)
            ->logOnlyDirty();
    }

    public function details()
    {
        return $this->hasOne(UserDetail::class);
    }

    public function clients()
    {
        return $this->belongsToMany(\App\Models\Client::class);
    }

    public function profitCenters()
    {
        return $this->belongsToMany(\App\Models\ProfitCenter::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps()
            ->whereNull('team_members.deleted_at');
    }

    public function teamMembers()
    {
        return $this->hasMany(\App\Models\TeamMember::class);
    }

    // Team where the user is manager
    public function managedTeam()
    {
        return $this->hasOne(Team::class, 'manager_user_id');
    }
}
