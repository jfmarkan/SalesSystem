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
                "email" => "markan.juanfrancisco@steinbacher.at",
                "email_verified_at" => now(),
                "password" => bcrypt("mju1234"),
                "role_id" => 1,
            ],
            [
                "name" => "Ute",
                "surname" => "Steinbacher",
                "username"=> "us",
                "email" => "steinbacher.ute@steinbacher.at",
                "email_verified_at" => now(),
                "password" => bcrypt("us1234"),
                "role_id" => 2,
            ],
            [
                "name" => "Roland",
                "surname" => "Hebbel",
                "username"=> "rh",
                "email" => "hebbel.roland@steinbacher.at",
                "email_verified_at" => now(),
                "password" => bcrypt("rh1234"),
                "role_id" => 2,
            ],
            [
                "name" => "Philipp",
                "surname" => "Schober",
                "username"=> "psc",
                "email" => "schober.philipp@steinbacher.at",
                "email_verified_at" => now(),
                "password" => bcrypt("psc1234"),
                "role_id" => 3,
            ],
            [
                "name" => "Günther",
                "surname" => "Mayr",
                "username"=> "gm",
                "email" => "mayr.guenther@steinbacher.at",
                "email_verified_at" => now(),
                "password" => bcrypt("gm1234"),
                "role_id" => 3,
            ],
            [
                "name" => "Stefan",
                "surname" => "Diechtler",
                "username"=> "sd",
                "email" => "diechtler.stefan@steinbacher.at",
                "email_verified_at" => now(),
                "password" => bcrypt("sd1234"),
                "role_id" => 3,
            ],
            [
                "name" => "Wolfgang",
                "surname" => "Herrmann",
                "username"=> "wh",
                "email" => "herrmann.wolfgang@steinbacher.at",
                "email_verified_at" => now(),
                "password" => bcrypt("wh1234"),
                "role_id" => 4,
            ],
            [
                "name" => "Franz",
                "surname" => "Priglinger",
                "username"=> "fp",
                "email" => "priglinger.franz@steinbacher.at",
                "email_verified_at" => now(),
                "password" => bcrypt("fp1234"),
                "role_id" => 4,
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
