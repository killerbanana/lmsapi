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
        $admins = [
            [
                'idnumber' => 'ADM001',
                'username' => 'admin1',
                'email' => 'admin1@example.com',
                'firstname' => 'Alice',
                'lastname' => 'Smith',
            ],
            [
                'idnumber' => 'ADM002',
                'username' => 'admin2',
                'email' => 'admin2@example.com',
                'firstname' => 'Bob',
                'lastname' => 'Johnson',
            ],
            [
                'idnumber' => 'ADM003',
                'username' => 'admin3',
                'email' => 'admin3@example.com',
                'firstname' => 'Carol',
                'lastname' => 'Lee',
            ],
        ];

        foreach ($admins as $admin) {
            // Create user entry
            DB::table('users')->insert([
                'idnumber' => $admin['idnumber'],
                'username' => $admin['username'],
                'usertype' => 'Administrator',
                'email' => $admin['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('admin123'), // Default password
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create admin table entry
            DB::table('admin')->insert([
                'idnumber' => $admin['idnumber'],
                'email' => $admin['email'],
                'photo' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSn8fHaLdaUpKOKTfHYd0KekveFT5Qu01D7sQ&s',
                'firstname' => $admin['firstname'],
                'lastname' => $admin['lastname'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('Admin user seeded successfully.');
    }
}
