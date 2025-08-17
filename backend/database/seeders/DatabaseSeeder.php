<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ClassificationSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            TeamSeeder::class,
            TeamMemberSeeder::class,
            ClientSeeder::class,
            SeasonalitySeeder::class,
            ProfitCenterSeeder::class,
            ClientProfitCenterSeeder::class,
            BudgetSeeder::class,
            SaleSeeder::class,
            ForecastSeeder::class,
        ]);

        \App\Models\User::find(1)?->assignRole('Super Admin');
    }
}
