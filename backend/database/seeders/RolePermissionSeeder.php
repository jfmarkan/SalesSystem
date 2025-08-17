<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run() {
        $superAdmin = Role::create(['name' => 'Super Admin']);
        $manager = Role::create(['name' => 'Admin']);
        $trainer = Role::create(['name' => 'Manager']);
        $employee = Role::create(['name' => 'Sales Rep']);
        $member = Role::create(['name' => 'KAM']);
        $user = Role::create(['name' => 'Logistics']);
    }
}
