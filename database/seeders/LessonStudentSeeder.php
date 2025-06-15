<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LessonStudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all lessons
        $lessons = DB::table('lessons')->get();

        foreach ($lessons as $lesson) {
            // Get students assigned to the same class
            $students = DB::table('class_students')
                ->where('class_id', $lesson->class_id)
                ->pluck('idnumber');

            // Assign lesson to all students in the same class
            foreach ($students as $studentId) {
                DB::table('lesson_student')->insert([
                    'lesson_id' => $lesson->id,
                    'idnumber' => $studentId,
                    'progress' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Lesson student seeded successfully.');
    }
}
