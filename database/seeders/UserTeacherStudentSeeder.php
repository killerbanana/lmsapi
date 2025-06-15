<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Faker\Factory as Faker;

class UserTeacherStudentSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $total = 10;

        // Fetch 50 random user photos
        $response = Http::get('https://randomuser.me/api/', [
            'results' => $total,
            'inc' => 'picture',
        ]);

        if (!$response->ok()) {
            $this->command->error('Failed to fetch random user photos.');
            return;
        }

        $photos = collect($response->json('results'))
            ->pluck('picture.large')
            ->all();

        for ($i = 1; $i <= $total; $i++) {
            $idnumber = sprintf('TCH%03d', $i);
            $username = "teacher{$i}";
            $email = "{$username}@example.com";
            $gender = $faker->randomElement(['male', 'female']);
            $photo = $photos[array_rand($photos)];

            // Insert user
            DB::table('users')->insert([
                'idnumber'          => $idnumber,
                'username'          => $username,
                'usertype'          => 'Teacher',
                'email'             => $email,
                'email_verified_at' => now(),
                'password'          => Hash::make('password123'),
                'remember_token'    => Str::random(10),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // Insert teacher profile
            DB::table('teachers')->insert([
                'idnumber'   => $idnumber,
                'firstname'  => $faker->firstName($gender),
                'lastname'   => $faker->lastName,
                'email'      => $email,
                'birthdate'  => $faker->date('Y-m-d', '-25 years'),
                'phone'      => $faker->phoneNumber,
                'address'    => $faker->address,
                'gender'     => $gender,
                'photo'      => $photo,
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("Seeded {$total} teachers (with random photos) successfully.");
    }
}
