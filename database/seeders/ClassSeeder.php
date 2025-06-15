<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        // Get all teachers (adjust if you use roles differently)
        $teachers = User::where('usertype', 'Teacher')->pluck('idnumber')->toArray();

        if (empty($teachers)) {
            $this->command->warn('No teachers found. Seed users first.');
            return;
        }

        for ($i = 1; $i <= 10; $i++) {
            $classId = strtoupper(Str::random(6));

            // Insert class
            DB::table('classes')->insert([
                'class_id' => $classId,
                'class_name' => 'Class ' . $i,
                'description' => 'This is class ' . $i,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign to random teacher
            DB::table('class_teachers')->insert([
                'class_id' => $classId,
                'idnumber' => $teachers[array_rand($teachers)],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Classes seeded successfully.');
    }
}
