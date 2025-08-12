<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classification extends Model
{
    use SoftDeletes;

    protected $table = 'classifications';

    protected $fillable = [
        'calssification', // Ojo: en tu migración está escrito así, no "classification"
    ];

    /**
     * Relación: una clasificación puede tener muchos clientes.
     */
    public function clients()
    {
        return $this->hasMany(Client::class, 'classification_id', 'id');
    }
}
