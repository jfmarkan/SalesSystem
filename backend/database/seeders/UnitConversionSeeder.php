<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\support\Carbon;

use App\Models\UnitConversion;

class UnitConversionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'profit_center_code' => '110',
                'from_unit'=>'m3',
                'factor_to_m3'=>1,
                'factor_to_euro'=>130,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '130',
                'from_unit'=>'lfm',
                'factor_to_m3'=>0.006841,
                'factor_to_euro'=>350,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '140',
                'from_unit'=>'lfm',
                'factor_to_m3'=>0.000976,
                'factor_to_euro'=>245,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '141',
                'from_unit'=>'lfm',
                'factor_to_m3'=>0.000819,
                'factor_to_euro'=>335,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '142',
                'from_unit'=>'lfm',
                'factor_to_m3'=>0.001129,
                'factor_to_euro'=>650,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '143',
                'from_unit'=>'m2',
                'factor_to_m3'=>0.004537,
                'factor_to_euro'=>205,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '144',
                'from_unit'=>'lfm',
                'factor_to_m3'=>0.001126,
                'factor_to_euro'=>120,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '160',
                'from_unit'=>'lfm',
                'factor_to_m3'=>0.004732,
                'factor_to_euro'=>400,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '170',
                'from_unit'=>'m3',
                'factor_to_m3'=>1,
                'factor_to_euro'=>60,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '171',
                'from_unit'=>'m3',
                'factor_to_m3'=>1,
                'factor_to_euro'=>81,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '173',
                'from_unit'=>'m2',
                'factor_to_m3'=>0.024347,
                'factor_to_euro'=>190,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '174',
                'from_unit'=>'m2',
                'factor_to_m3'=>0.028402,
                'factor_to_euro'=>77,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'profit_center_code' => '175',
                'from_unit'=>'m3',
                'factor_to_m3'=>1,
                'factor_to_euro'=>117,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
        ];

        foreach ($units as $unit){
            $newUnit = new UnitConversion();
            $newUnit->profit_center_code = $unit['profit_center_code'];
            $newUnit->from_unit = $unit['from_unit'];
            $newUnit->factor_to_m3 = $unit['factor_to_m3'];
            $newUnit->factor_to_euro = $unit['factor_to_euro'];
            $newUnit->created_at = $unit['created_at'];
            $newUnit->updated_at = $unit['updated_at'];
            $newUnit->deleted_at = $unit['deleted_at'];
            $newUnit->save();
        }
    }
}
