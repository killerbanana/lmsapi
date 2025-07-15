<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Faker\Factory as Faker;
use Illuminate\Http\Client\RequestException;

class StudentClassSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $classIds = DB::table('classes')->pluck('class_id')->toArray();
        $total = 50;

        if (empty($classIds)) {
            $this->command->error('No classes found in the database. Please seed classes first.');
            return;
        }

        try {
            // Fetch random user data including picture, gender, name, and location
            $response = Http::get('https://randomuser.me/api/', [
                'results' => $total,
                'inc' => 'picture,gender,name,location,phone,login',
            ]);
            $response->throw(); // Throw an exception for 4xx or 5xx status codes
            $randomUsers = collect($response->json('results'));
        } catch (RequestException $e) {
            $this->command->error('Failed to fetch student photos: ' . $e->getMessage());
            return;
        }

        for ($i = 0; $i < $total; $i++) {
            $user = $randomUsers[$i];
            $studentIdNumber = 'STD' . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
            $studentEmail = "student" . ($i + 1) . "@example.com";
            $studentFirstName = $user['name']['first'];
            $studentLastName = $user['name']['last'];
            $gender = $user['gender'];
            $photo = $user['picture']['large'];

            // 1. Create Student User
            DB::table('users')->insert([
                'idnumber' => $studentIdNumber,
                'username' => $user['login']['username'],
                'usertype' => 'Student',
                'email' => $studentEmail,
                'email_verified_at' => now(),
                'password' => Hash::make('student123'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Create Student Profile
            DB::table('students')->insert([
                'idnumber' => $studentIdNumber,
                'email' => $studentEmail,
                'firstname' => $studentFirstName,
                'lastname' => $studentLastName,
                'gender' => $gender,
                'birthdate' => $faker->date('Y-m-d', '-15 years'),
                'phone' => $user['phone'],
                'address' => "{$user['location']['street']['number']} {$user['location']['street']['name']}, {$user['location']['city']}, {$user['location']['state']}, {$user['location']['country']}",
                'fathername' => $faker->name('male'),
                'fathercontact' => $faker->phoneNumber,
                'mothername' => $faker->name('female'),
                'mothercontact' => $faker->phoneNumber,
                'guardian_name' => $faker->name,
                'guardian_contact' => $faker->phoneNumber,
                'photo' => $photo,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Create Parent User and Profile
            $parentIdNumber = $studentIdNumber . '-parent';
            DB::table('users')->insert([
                'idnumber' => $parentIdNumber,
                'username' => $parentIdNumber,
                'usertype' => 'Parent',
                'email' => $parentIdNumber . '@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('parent123'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('parent_tbl')->insert([
                'idnumber' => $parentIdNumber,
                'linked_id' => $studentIdNumber,
                'firstname' => $faker->firstName,
                'lastname' => $studentLastName, // Using student's last name for consistency
                'email' => $parentIdNumber . '@example.com',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4. Assign student to up to 2 random classes
            $assignedClassIds = collect($classIds)->random(min(2, count($classIds)))->all();
            foreach ($assignedClassIds as $classId) {
                DB::table('class_students')->insert([
                    'idnumber' => $studentIdNumber,
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