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
        ]);

        \App\Models\User::find(1)?->assignRole('superadmin');
    }
}
