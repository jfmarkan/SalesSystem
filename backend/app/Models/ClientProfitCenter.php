<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientProfitCenter extends Model
{
    use SoftDeletes;

    protected $table = 'client_profit_centers';

    protected $fillable = [
        'client_group_number',
        'profit_center_code',
    ];

    /**
     * Relación: pertenece a un cliente.
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_group_number');
    }

    /**
     * Relación: pertenece a un profit center.
     */
    public function profitCenter()
    {
        return $this->belongsTo(ProfitCenter::class, 'profit_center_code');
    }

    /**
     * Relación: tiene muchas asignaciones.
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class, 'client_profit_center_id', 'id');
    }
}
