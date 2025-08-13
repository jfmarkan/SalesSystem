<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProfitCenterSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::parse('2025-08-12');

        $profitcenters = [
            [
                'profit_center_code' => 110,
                'profit_center_name' => 'PUR - Platten',                    
                'seasonality_id' => 1
            ],
            [
                'profit_center_code' => 130,
                'profit_center_name' => 'PUR - Rohrschalen',                
                'seasonality_id' => 2
            ],
            [
                'profit_center_code' => 140,
                'profit_center_name' => 'LDPE - Schläuche',                 
                'seasonality_id' => 3
            ],
            [
                'profit_center_code' => 141,
                'profit_center_name' => 'LDPE - Abflussisolierung/Dünnwand',
                'seasonality_id' => 4
            ],
            [
                'profit_center_code' => 142,
                'profit_center_name' => 'LDPE - HKS',                       
                'seasonality_id' => 5
            ],
            [
                'profit_center_code' => 143,
                'profit_center_name' => 'LDPE - Matten',                    
                'seasonality_id' => 6
            ],
            [
                'profit_center_code' => 144,
                'profit_center_name' => 'LDPE - Randstreifen',              
                'seasonality_id' => 7
            ],
            [
                'profit_center_code' => 160,
                'profit_center_name' => 'Steinwolle - Rohrschalen',         
                'seasonality_id' => 8
            ],
            [
                'profit_center_code' => 170,
                'profit_center_name' => 'EPS & Fassade Grau',               
                'seasonality_id' => 9
            ],
            [
                'profit_center_code' => 171,
                'profit_center_name' => 'Automatenplatten',                 
                'seasonality_id' => 10
            ],
            [
                'profit_center_code' => 173,
                'profit_center_name' => 'Noppenplatten',                    
                'seasonality_id' => 12
            ],
            [
                'profit_center_code' => 174,
                'profit_center_name' => 'EPS - Rollenware',                 
                'seasonality_id' => 13
            ],
            [
                'profit_center_code' => 175,
                'profit_center_name' => 'Verbundelemente',                  
                'seasonality_id' => 14
            ],
        ];

        foreach ($profitcenters as $pc) {
            DB::table('profit_centers')->insert([
                'profit_center_code' => $pc['profit_center_code'],
                'profit_center_name' => $pc['profit_center_name'],
                'seasonality_id'     => $pc['seasonality_id'],
                'created_at'         => $now,
                'updated_at'         => null,
                'deleted_at'         => null,
            ]);
        }
    }
}
