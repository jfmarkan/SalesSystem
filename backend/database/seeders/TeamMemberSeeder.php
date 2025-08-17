<?php

namespace Database\Seeders;

use App\Models\TeamMember;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\support\Carbon;

class TeamMemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teamMembers = [
            [
                'team_id' => 1,
                'user_id' => 7,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 1,
                'user_id' => 8,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 1,
                'user_id' => 9,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 1,
                'user_id' => 12,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 1,
                'user_id' => 15,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 1,
                'user_id' => 18,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 2,
                'user_id' => 7,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 2,
                'user_id' => 10,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 2,
                'user_id' => 11,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 2,
                'user_id' => 12,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 2,
                'user_id' => 13,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 2,
                'user_id' => 14,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 2,
                'user_id' => 16,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
            [
                'team_id' => 2,
                'user_id' => 17,
                'role' => 'SALES_REP',
                'created_at' => Now(),
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ],
        ];

        foreach ($teamMembers as $teamMember) {
            $newTeamMember = new TeamMember();
            $newTeamMember->team_id = $teamMember['team_id'];
            $newTeamMember->user_id = $teamMember['user_id'];
            $newTeamMember->role = $teamMember['role'];
            $newTeamMember->created_at = $teamMember['created_at'];
            $newTeamMember->updated_at = $teamMember['updated_at'];
            $newTeamMember->deleted_at = $teamMember['deleted_at'];
            $newTeamMember->save();
        };
    }
}
