<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use DB;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            'idnumber' => 'ADM001',
            'username' => 'admin',
            'usertype' => 'Administrator',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('admin123'), // use bcrypt hashing
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin')->insert([
            'idnumber' => 'ADM001',
            'email' => 'admin@example.com',
            'photo' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSn8fHaLdaUpKOKTfHYd0KekveFT5Qu01D7sQ&s',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
