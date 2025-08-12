<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClassificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classifications = [
            [
                'classification' => "A"
            ],
            [
                'classification' => "B"
            ],
            [
                'classification' => "C"
            ],
            [
                'classification' => "D"
            ],
            [
                'classification' => "X"
            ],
            [
                'classification' => "PA"
            ],
            [
                'classification' => "PB"
            ],
        ];

        foreach ($classifications as $classification){
            $newClassification = new Classification();
            $newClassification->classification = $clasification['classification'];
            $newClassification->save();
        }
    }
}
