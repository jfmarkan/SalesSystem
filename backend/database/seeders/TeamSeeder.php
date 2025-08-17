<?php

namespace Database\Seeders;

use App\Models\Team;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Carbon;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams =[
            [
                'name' => 'Alpha',
                'manager_user_id' => 4,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'name' => 'Bravo',
                'manager_user_id' => 5,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'name' => 'Charlie',
                'manager_user_id' => 6,
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
        ];

        foreach ($teams as $team){
            $newTeam = new Team();
            $newTeam->name = $team['name'];
            $newTeam->manager_user_id = $team['manager_user_id'];
            $newTeam->created_at = $team['created_at'];
            $newTeam->updated_at = $team['updated_at'];
            $newTeam->deleted_at = $team['deleted_at'];
            $newTeam->save();
        }
    }
}
