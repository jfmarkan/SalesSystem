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
        'username',
        'email',
        'password',
        'otp',
        'otp_expires_at',
        'role_id'
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
}