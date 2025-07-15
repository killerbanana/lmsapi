<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            UserTeacherStudentSeeder::class,
            ClassSeeder::class,
            StudentClassSeeder::class,
            LessonSeeder::class,
            LessonStudentSeeder::class,
            LessonSectionSeeder::class,
        ]);
    }
}
