<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LessonSectionSeeder extends Seeder
{
    public function run(): void
    {
        $lessons = DB::table('lessons')->get();

        foreach ($lessons as $lesson) {
            $sectionCount = rand(5, 10);

            for ($i = 0; $i < $sectionCount; $i++) {
                $type = rand(0, 1) === 0 ? 'content' : 'assessment';
                $subtype = $type === 'content' ? 'page' : 'quiz';

                // Insert into sections table
                $sectionId = DB::table('sections')->insertGetId([
                    'lesson_id' => $lesson->id,
                    'type' => $type,
                    'subtype' => $subtype,
                    'require_for_completion' => rand(0, 1),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($type === 'content') {
                    // Add a content section
                    DB::table('content_sections')->insert([
                        'section_id' => $sectionId,
                        'introduction' => 'This is the introduction to content section ' . $i,
                        'content' => '<p>This is sample content for lesson ID ' . $lesson->id . '</p>',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    // Add a quiz assessment
                    $quizId = DB::table('quiz_assessments')->insertGetId([
                        'section_id' => $sectionId,
                        'title' => 'Quiz ' . Str::random(5),
                        'instructions' => 'Answer all questions.',
                        'points' => rand(10, 100),
                        'category' => 'General',
                        'start' => Carbon::now()->subDays(rand(0, 3)),
                        'due' => Carbon::now()->addDays(rand(2, 10)),
                        'grading_scale' => 'Default',
                        'grading' => 'Normal',
                        'max_attempts' => rand(1, 3),
                        'allow_late' => rand(0, 1),
                        'timed' => rand(0, 1),
                        'instant_feedback' => rand(0, 1),
                        'release_grades' => 'Instant',
                        'grading_method' => 'latest',
                        'disable_past_due' => rand(0, 1),
                        'autocomplete_on_retake' => rand(0, 1),
                        'randomize_order' => true,
                        'allow_review' => true,
                        'allow_jump' => true,
                        'show_in_results' => json_encode(['score', 'answers']),
                        'library' => 'Personal',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Get students enrolled in the lesson's class
                    $studentIdnumbers = DB::table('class_students')
                        ->where('class_id', $lesson->class_id)
                        ->pluck('idnumber');

                    foreach ($studentIdnumbers as $studentIdnumber) {
                        DB::table('quiz_assessment_student')->insert([
                            'quiz_assessment_id' => $quizId,
                            'student_idnumber' => $studentIdnumber,
                            'score' => null,
                            'submitted_at' => null,
                            'attempts' => 0,
                            'answer_text' => null,
                            'file_path' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
        $this->command->info('Lessons section seeded successfully.');
    }
}
