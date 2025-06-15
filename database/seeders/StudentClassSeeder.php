<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Faker\Factory as Faker;

class StudentClassSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $classIds = DB::table('classes')->pluck('class_id')->toArray();
        $total = 100;

        // Fetch random user photos
        $response = Http::get('https://randomuser.me/api/', [
            'results' => $total,
            'inc' => 'picture,gender',
        ]);

        if (!$response->ok()) {
            $this->command->error('Failed to fetch student photos.');
            return;
        }

        $randomUsers = collect($response->json('results'));

        for ($i = 1; $i <= $total; $i++) {
            $idnumber = 'STD' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $email = "student{$i}@example.com";
            $user = $randomUsers[$i - 1];
            $photo = $user['picture']['large'];
            $gender = $user['gender'];

            // Create student user
            DB::table('users')->insert([
                'idnumber' => $idnumber,
                'username' => "student{$i}",
                'usertype' => 'Student',
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make('student123'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create student profile
            DB::table('students')->insert([
                'idnumber'         => $idnumber,
                'email'            => $email,
                'firstname'        => $faker->firstName($gender),
                'lastname'         => $faker->lastName,
                'gender'           => $gender,
                'birthdate'        => $faker->date('Y-m-d', '-15 years'),
                'phone'            => $faker->phoneNumber,
                'address'          => $faker->address,
                'fathername'       => 'Father of ' . $i,
                'fathercontact'    => $faker->phoneNumber,
                'mothername'       => 'Mother of ' . $i,
                'mothercontact'    => $faker->phoneNumber,
                'guardian_contact' => $faker->phoneNumber,
                'photo'            => $photo,
                'status'           => 'active',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Parents (Father)
            $fatherId = $idnumber . '-father';
            DB::table('users')->insert([
                'idnumber' => $fatherId,
                'username' => $fatherId,
                'usertype' => 'Parent',
                'email' => $fatherId . '@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('parent123'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('parent_tbl')->insert([
                'idnumber' => $fatherId,
                'linked_id' => $idnumber,
                'firstname' => 'Father of',
                'lastname' => $i,
                'email' => $fatherId . '@example.com',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Parents (Mother)
            $motherId = $idnumber . '-mother';
            DB::table('users')->insert([
                'idnumber' => $motherId,
                'username' => $motherId,
                'usertype' => 'Parent',
                'email' => $motherId . '@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('parent123'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('parent_tbl')->insert([
                'idnumber' => $motherId,
                'linked_id' => $idnumber,
                'firstname' => 'Mother of',
                'lastname' => $i,
                'email' => $motherId . '@example.com',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign student to 2 random classes
            $assignedClassIds = collect($classIds)->random(min(2, count($classIds)));
            foreach ($assignedClassIds as $classId) {
                DB::table('class_students')->insert([
                    'idnumber' => $idnumber,
                    'class_id' => $classId,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info("Seeded {$total} students (with photos) successfully.");
    }
}
