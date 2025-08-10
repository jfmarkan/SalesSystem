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
        $superAdmin = Role::create(['name' => 'superadmin']);
        $manager = Role::create(['name' => 'manager']);
        $trainer = Role::create(['name' => 'trainer']);
        $employee = Role::create(['name' => 'employee']);
        $member = Role::create(['name' => 'member']);
        $user = Role::create(['name' => 'user']);
    }
}
