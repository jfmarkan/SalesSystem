<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Sale extends Model
{
    use SoftDeletes;

    protected $table = 'sales';

    protected $fillable = [
        'client_profit_center_id',
        'fiscal_year',
        'month',
        'cubic_meters',
        'sales_units',
        'euros',
    ];

    protected $casts = [
        'client_profit_center_id' => 'int',
        'fiscal_year'             => 'int',
        'month'                   => 'int',
        'cubic_meters'            => 'float',
        'sales_units'             => 'float',
        'euros'                   => 'float',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
        'deleted_at'              => 'datetime',
    ];

    // Unidades "lógicas" que va a pedir el front / endpoints
    public const UNIT_M3    = 'm3';     // metros cúbicos
    public const UNIT_UNITS = 'vk_eh';  // unidades / VK-EH
    public const UNIT_EUR   = 'eur';    // euros

    public function cpc()
    {
        return $this->belongsTo(ClientProfitCenter::class, 'client_profit_center_id');
    }

    public function scopeOfPeriod(Builder $q, int $fy, ?int $month = null): Builder
    {
        $q->where('fiscal_year', $fy);
        if ($month !== null) {
            $q->where('month', $month);
        }

        return $q;
    }

    /**
     * Dado un código de unidad (m3 / eur / vk_eh) devuelve la
     * columna real de BD que hay que usar para agregar/seleccionar.
     */
    public static function columnForUnit(string $unit): string
    {
        return match (strtolower($unit)) {
            self::UNIT_M3    => 'cubic_meters',
            self::UNIT_EUR   => 'euros',
            self::UNIT_UNITS => 'sales_units',
            default          => 'cubic_meters', // fallback razonable
        };
    }
}
