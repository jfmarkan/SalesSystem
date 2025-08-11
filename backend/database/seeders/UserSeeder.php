<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                "name" => "Juan Francisco",
                "surname" => "Markan",
                "username"=> "mju",
                "email" => "jfmarkan@gmail.com",
                "email_verified_at" => now(),
                "password" => bcrypt("jfMS-31531055!"),
                "role_id" => 1,
            ],
        ];

        foreach ($users as $user){
            $newUser = new User();
            $newUser->first_name = $user['name'];
            $newUser->last_name = $user['surname'];
            $newUser->username = $user['username'];
            $newUser->email = $user['email'];
            $newUser->email_verified_at = $user['email_verified_at'];
            $newUser->password = $user['password'];
            $newUser->role_id = $user['role_id'];
            $newUser->save();
        }
    }
}
