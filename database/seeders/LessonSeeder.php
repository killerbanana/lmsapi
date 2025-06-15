<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LessonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all class IDs and teacher ID numbers
        $classIds = DB::table('classes')->pluck('class_id')->toArray();
        $teacherIdNumbers = DB::table('users')->where('usertype', 'Teacher')->pluck('idnumber')->toArray();

        // Guard clause if no data exists
        if (empty($classIds) || empty($teacherIdNumbers)) {
            $this->command->warn('No classes or teachers found to seed lessons.');
            return;
        }

        for ($i = 1; $i <= 50; $i++) {
            $lessonName = 'Lesson ' . $i;
            $teacherId = collect($teacherIdNumbers)->random();
            $classId = collect($classIds)->random();

            DB::table('lessons')->insert([
                'name' => $lessonName,
                'idnumber' => $teacherId,
                'class_id' => $classId,
                'description' => 'This is a description for ' . $lessonName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Lessons seeded successfully.');
    }
}
