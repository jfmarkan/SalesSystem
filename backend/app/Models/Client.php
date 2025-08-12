<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $table = 'clients';

    protected $primaryKey = 'client_group_number';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'client_group_number',
        'client_name',
        'classification_id',
    ];

    /**
     * Relación: un cliente pertenece a una clasificación.
     */
    public function classification()
    {
        return $this->belongsTo(Classification::class, 'classification_id', 'id');
    }

    /**
     * Relación: un cliente puede tener muchos vínculos cliente-profit center.
     */
    public function clientProfitCenters()
    {
        return $this->hasMany(ClientProfitCenter::class, 'client_group_number', 'client_group_number');
    }
}
