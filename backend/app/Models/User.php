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

    public function saludo($formal = true)
    {
        if ($formal) {
            if ($this->gender === 'M') {
                return "Estimado Sr. {$this->last_name}";
            } elseif ($this->gender === 'F') {
                return "Estimada Sra. {$this->last_name}";
            } else {
                return "Estimado/a {$this->last_name}";
            }
        } else {
            return "Hola {$this->first_name}";
        }
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

    public function businessesOwned()
    {
        return $this->hasMany(Business::class, 'owner_id');
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function certifications()
    {
        return $this->hasMany(Certification::class);
    }
}
