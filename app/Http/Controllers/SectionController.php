<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\ContentSection;
use App\Models\SectionResource;
use App\Models\SectionQuiz;
use App\Models\QuizAssessment;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; 
use App\Models\TeacherClass;
use App\Models\Students;
use App\Models\DropboxAssessment;
use Illuminate\Support\Facades\Auth;

class SectionController extends Controller
{
    /**
     * Create a new section for a lesson.
     */

    public function getLessonSectionsWithTypesAndStudents($lessonId)
    {
        $sections = Section::with([
            'dropboxAssessments.students',
            'quizAssessments.students'
        ])->where('lesson_id', $lessonId)->get();

        $formattedSections = [];

        foreach ($sections as $section) {
            $sectionData = [
                'section_id' => $section->id,
                'section_title' => $section->title,
                'type' => $section->subtype,
            ];

            if ($section->subtype === 'dropbox') {
                $sectionData['dropbox'] = $section->dropboxAssessments->map(function ($dropbox) {
                    return [
                        'dropbox_id' => $dropbox->id,
                        'title' => $dropbox->title,
                        'students' => $dropbox->students->pluck('idnumber'),
                    ];
                });
            } elseif ($section->subtype === 'quiz') {
                $sectionData['quiz'] = $section->quizAssessments->map(function ($quiz) {
                    return [
                        'quiz_id' => $quiz->id,
                        'title' => $quiz->title,
                        'instructions' => $quiz->instructions,
                        'students' => $quiz->students->pluck('idnumber'),
                    ];
                });
            }

            $formattedSections[] = $sectionData;
        }

        return response()->json([
            'lessonId' => (int) $lessonId,
            'sections' => $formattedSections
        ]);
    }

    public function getSectionStudent($lessonId)
    {
        $user = Auth::user();

        if (!$user || $user->usertype !== 'Student') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $studentIdnumber = $user->idnumber;

        // Get sections with subtype 'quiz' and only quizzes assigned to the student
        $sections = Section::where('lesson_id', $lessonId)
            ->where('subtype', 'quiz')
            ->with(['quizAssessments' => function ($query) use ($studentIdnumber) {
                $query->whereHas('students', function ($q) use ($studentIdnumber) {
                    $q->where('student_idnumber', $studentIdnumber);
                });
            }])
            ->get();

        $formattedSections = [];

        foreach ($sections as $section) {
            if ($section->quizAssessments->isEmpty()) {
                continue; // Skip sections with no quizzes for this student
            }

            $formattedSections[] = [
                'id' => $section->id,
                'lesson_id' => $section->lesson_id,
                'type' => $section->type,
                'subtype' => $section->subtype,
                'require_for_completion' => $section->require_for_completion,
                'created_at' => $section->created_at,
                'updated_at' => $section->updated_at,
                'quiz_assessments' => $section->quizAssessments->map(function ($quiz) {
                    return $quiz->toArray();
                }),
            ];
        }

        // Group by lesson_id (even if it's only one lesson)
        $grouped = collect($formattedSections)
            ->groupBy('lesson_id')
            ->map(function ($sections, $lessonId) {
                return [
                    'lesson_id' => (int) $lessonId,
                    'sections' => $sections->values()
                ];
            });

        return response()->json([
            'lessons' => $grouped
        ]);
    }




    public function create(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => 'required|integer|exists:lessons,id',
            'title' => 'nullable|string',
            'introduction' => 'nullable|string',
            'require_for_completion' => 'boolean',
            'completion_time_estimate' => 'nullable|integer|min:1',

            'type' => 'required|in:content,assessment,other',
            'subtype' => 'required|string',

            'files' => 'nullable|array',
            'files.*' => 'file|max:10240',
            'resource_names' => 'nullable|array',
            'resource_names.*' => 'required_with:files|string',
            'resource_types' => 'nullable|array',
            'resource_types.*' => 'nullable|string',

            'completion_actions' => 'nullable|array',
            'completion_actions.*.action_type' => 'required|string',
            'completion_actions.*.parameters' => 'nullable|array',

            'page_content' => 'nullable|string',
            'quiz_id' => 'nullable|integer|exists:quiz_assessments,id',
        ]);

        $userIdnumber = auth()->user()->idnumber;

        // Verify lesson belongs to class the teacher is assigned to
        $lesson = Lesson::where('id', $validated['lesson_id'])
            ->whereIn('class_id', function ($query) use ($userIdnumber) {
                $query->select('class_id')
                    ->from('class_teachers')
                    ->where('idnumber', $userIdnumber);
            })
            ->first();

        if (!$lesson) {
            abort(403, 'You are not authorized to create a section for this lesson.');
        }

        // Create section
        $section = Section::create([
            'lesson_id' => $validated['lesson_id'],
            'type' => $validated['type'],
            'subtype' => $validated['subtype'],
            'require_for_completion' => $validated['require_for_completion'] ?? false,
        ]);

        // Handle content section
        if ($validated['type'] === 'content' && $validated['subtype'] === 'page') {
            ContentSection::create([
                'section_id' => $section->id,
                'introduction' => $validated['introduction'] ?? null,
                'content' => $validated['page_content'] ?? '',
            ]);
        }

        // Handle file uploads to Firebase
        if ($request->hasFile('files')) {
            $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
            $bucket = $firebase->createStorage()->getBucket();

            foreach ($request->file('files') as $index => $file) {
                $firebaseFilePath = 'resources/resource_' . uniqid() . '_' . $file->getClientOriginalName();

                $bucket->upload(
                    fopen($file->getRealPath(), 'r'),
                    ['name' => $firebaseFilePath]
                );

                $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";

                SectionResource::create([
                    'section_id' => $section->id,
                    'name' => $validated['resource_names'][$index] ?? 'Unnamed Resource',
                    'type' => $validated['resource_types'][$index] ?? 'unknown',
                    'url' => $url,
                ]);
            }
        }

        $lessonStudentIds = DB::table('lesson_student')->where('lesson_id', $section->lesson_id)->pluck('idnumber');

        $sectionProgressData = $lessonStudentIds->map(function ($idnumber) use ($section) {
            return [
                'idnumber' => $idnumber,
                'section_id' => $section->id,
                'status' => 'not_started',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        DB::table('section_progress')->insert($sectionProgressData);

        return response()->json(['message' => 'Section created successfully'], 201);
    }

    public function createAssessment(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => 'required|integer|exists:lessons,id',
            'title' => 'nullable|string',
            'introduction' => 'nullable|string',
            'require_for_completion' => 'boolean',
            'completion_time_estimate' => 'nullable|integer|min:1',

            'type' => 'required|in:content,assessment,other',
            'subtype' => 'required|in:quiz,dropbox',

            'files' => 'nullable|array',
            'files.*' => 'file|max:10240',
            'resource_names' => 'nullable|array',
            'resource_names.*' => 'required_with:files|string',
            'resource_types' => 'nullable|array',
            'resource_types.*' => 'nullable|string',

            'completion_actions' => 'nullable|array',
            'completion_actions.*.action_type' => 'required|string',
            'completion_actions.*.parameters' => 'nullable|array',

            'page_content' => 'nullable|string',

            // Additional dynamic fields
            'points' => 'nullable|integer|min:0',
            'max_score' => 'nullable|integer|min:0',
            'category' => 'nullable|string',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'grading_scale' => 'nullable|string|in:Default,Custom',
            'max_attempts' => 'nullable|integer|min:1',
            'allow_late' => 'nullable|boolean',
            'grading' => 'nullable|in:Normal,Bonus,Penalty',
            'instructions' => 'nullable|string',
            'timed' => 'nullable|string',
            'instant_feedback' => 'nullable|string',
            'instant_feedback' => 'nullable|string',
        ]);


        $userIdnumber = auth()->user()->idnumber;

        $lesson = Lesson::where('id', $validated['lesson_id'])
            ->whereIn('class_id', function ($query) use ($userIdnumber) {
                $query->select('class_id')
                    ->from('class_teachers')
                    ->where('idnumber', $userIdnumber);
            })
            ->first();

        if (!$lesson) {
            abort(403, 'You are not authorized to create a section for this lesson.');
        }

        // Create the section
        $section = Section::create([
            'lesson_id' => $validated['lesson_id'],
            'type' => $validated['type'],
            'subtype' => $validated['subtype'],
            'require_for_completion' => $validated['require_for_completion'] ?? false,
        ]);

        // Handle Quiz or Dropbox creation
        if ($validated['type'] === 'assessment' && $validated['subtype'] === 'quiz') {
            $quiz = QuizAssessment::create([
                'section_id' => $section->id,
                'title' => $validated['title'] ?? 'Untitled Quiz',
                'instructions' => $validated['instructions'] ?? 'Untitled Quiz',
                'points' => $validated['points'],
                'category' => $validated['category'],
                'start' => now(),
                'due' => now()->addDays(7),
                'grading_scale' => $validated['grading_scale'] ?? 'Default',
                'grading' => $validated['grading'] ?? 'Normal',
                'max_attempts' => $validated['max_attempts'] ?? 1,
                'allow_late' => $validated['allow_late'] ?? false,
                'timed' => $validated['timed'] ?? false,
                'instant_feedback' => $validated['instant_feedback'] ?? false,
                'release_grades' => $validated['release_grades'] ?? 'Instant',
                'grading_method' => $validated['grading_method'] ?? 'latest',
                'disable_past_due' => $validated['disable_past_due'] ?? false,
                'autocomplete_on_retake' => $validated['autocomplete_on_retake'] ?? false,
                'randomize_order' => $validated['randomize_order'] ?? true,
                'allow_review' => $validated['allow_review'] ?? true,
                'allow_jump' => $validated['allow_jump'] ?? true,
                'show_in_results' => $validated['show_in_results'] ?? [],
                'library' => $validated['library'] ?? 'Personal',
            ]);

            $studentIdnumbers = DB::table('lesson_student')
                ->where('lesson_id', $section->lesson_id)
                ->pluck('idnumber');

            $pivotData = $studentIdnumbers->map(function ($idnumber) use ($quiz) {
                return [
                    'quiz_assessment_id' => $quiz->id,
                    'student_idnumber' => $idnumber,
                    'score' => null,
                    'submitted_at' => null,
                    'attempts' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            DB::table('quiz_assessment_student')->insert($pivotData);

        } elseif ($validated['type'] === 'assessment' && $validated['subtype'] === 'dropbox') {
            $dropbox = DropboxAssessment::create([
                'section_id' => $section->id,
                'title' => $validated['title'] ?? 'Untitled Dropbox',
                'instructions' => $validated['instructions'] ?? null,
                'points' => $validated['points'] ?? null,
                'category' => $validated['category'] ?? null,
                'start_date' => now(),
                'due_date' => now()->addDays(7),
                'grading_scale' => 'Default',
                'grading' => 'Normal',
                'max_attempts' => $validated['max_attempts'] ??1,
                'allow_late' => $validated['allow_late'] ??false,
                'timed' => $validated['timed'] ??false,
                'instant_feedback' =>$validated['instant_feedback'] ?? false,
                'release_grades' => $validated['release_grades'] ??'Instant',
                'grading_method' => $validated['grading_method'] ??'latest',
                'disable_past_due' => $validated['disable_past_due'] ??false,
                'autocomplete_on_retake' => $validated['autocomplete_on_retake'] ??false,
                'randomize_order' => $validated['randomize_order'] ??true,
                'allow_review' => $validated['allow_review'] ??true,
                'allow_jump' => $validated['allow_jump'] ??true,
                'show_in_results' => $validated['show_in_results'] ??json_encode([]),
                'library' => 'Personal',
            ]);

            // 🔗 Link students to dropbox
            $studentIdnumbers = DB::table('lesson_student')
                ->where('lesson_id', $section->lesson_id)
                ->pluck('idnumber');

            $pivotData = $studentIdnumbers->map(function ($idnumber) use ($dropbox) {
                return [
                    'dropbox_assessment_id' => $dropbox->id,
                    'student_idnumber' => $idnumber,
                    'score' => null,
                    'submitted_at' => null,
                    'attempts' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            DB::table('dropbox_assessment_student')->insert($pivotData);
        }

        // 📁 Handle file uploads (Firebase)
        if ($request->hasFile('files')) {
            $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
            $bucket = $firebase->createStorage()->getBucket();

            foreach ($request->file('files') as $index => $file) {
                $firebaseFilePath = 'resources/resource_' . uniqid() . '_' . $file->getClientOriginalName();

                $bucket->upload(
                    fopen($file->getRealPath(), 'r'),
                    ['name' => $firebaseFilePath]
                );

                $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";

                SectionResource::create([
                    'section_id' => $section->id,
                    'name' => $validated['resource_names'][$index] ?? 'Unnamed Resource',
                    'type' => $validated['resource_types'][$index] ?? 'unknown',
                    'url' => $url,
                ]);
            }
        }

        // 📊 Track section progress
        $lessonStudentIds = DB::table('lesson_student')->where('lesson_id', $section->lesson_id)->pluck('idnumber');

        $sectionProgressData = $lessonStudentIds->map(function ($idnumber) use ($section) {
            return [
                'idnumber' => $idnumber,
                'section_id' => $section->id,
                'status' => 'not_started',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        DB::table('section_progress')->insert($sectionProgressData);

        return response()->json(['message' => 'Section created successfully'], 201);
    }



    public function getAllSection(Request $request)
    {
        $user = Auth::user();
        $requestedClassId = $request->query('class_id');

        if (!in_array($user->usertype, ['Administrator', 'Teacher', 'Student'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Get allowed class IDs based on role
        $classIds = [];

        if ($user->usertype === 'Teacher') {
            $classIds = \DB::table('class_teachers')
                ->where('idnumber', $user->idnumber)
                ->pluck('class_id')
                ->toArray();
        } elseif ($user->usertype === 'Student') {
            $classIds = \DB::table('class_students')
                ->where('idnumber', $user->idnumber)
                ->pluck('class_id')
                ->toArray();
        }

        // If a specific class ID is requested, ensure the user has access
        if ($requestedClassId) {
            if (!in_array($requestedClassId, $classIds)) {
                return response()->json(['message' => 'Access to this class is not allowed.'], 403);
            }
            $classIds = [$requestedClassId];
        }

        // Fetch lessons from allowed classes
        $lessons = \App\Models\Lesson::with('class')
            ->whereIn('class_id', $classIds)
            ->get();

        $lessonIds = $lessons->pluck('id')->toArray();

        // Fetch related sections
        $sections = \App\Models\Section::with([
            'resources',
            'completionActions',
            'lesson',
            'contentSections',
            'assessmentSection'
        ])
        ->whereIn('lesson_id', $lessonIds)
        ->get();

        // Group by class → lessons → sections
        $grouped = $lessons->groupBy('class_id')->map(function ($classLessons, $classId) use ($sections) {
            return [
                'class_id' => $classId,
                'lessons' => $classLessons->map(function ($lesson) use ($sections) {
                    return [
                        'lesson' => $lesson,
                        'sections' => $sections->where('lesson_id', $lesson->id)->values(),
                    ];
                })->filter(fn ($item) => $item['sections']->isNotEmpty())->values()
            ];
        })->values();

        return response()->json([
            'message' => 'Sections grouped by class and lesson',
            'data' => $grouped,
        ]);
    }



    public function updateSectionProgress(Request $request)
    {
        $request->validate([
            'section_id' => 'required|exists:sections,id',
            'idnumber' => 'required|exists:users,idnumber',
            'status' => 'required|in:not_started,in_progress,completed',
        ]);

        $section = DB::table('sections')->where('id', $request->section_id)->first();

        if (!$section) {
            return response()->json(['message' => 'Section not found'], 404);
        }

        // Update or create section_progress
        DB::table('section_progress')->updateOrInsert(
            [
                'section_id' => $section->id,
                'idnumber' => $request->idnumber,
            ],
            [
                'status' => $request->status,
                'started_at' => $request->status === 'in_progress' ? Carbon::now() : null,
                'completed_at' => $request->status === 'completed' ? Carbon::now() : null,
                'updated_at' => now(),
            ]
        );

        // Now calculate new lesson progress
        $lessonId = $section->lesson_id;

        // Get total required sections
        $totalRequiredSections = DB::table('sections')
            ->where('lesson_id', $lessonId)
            ->where('require_for_completion', true)
            ->count();

        // Get how many required sections this student has completed
        $completedSections = DB::table('section_progress')
            ->join('sections', 'sections.id', '=', 'section_progress.section_id')
            ->where('sections.lesson_id', $lessonId)
            ->where('section_progress.idnumber', $request->idnumber)
            ->where('sections.require_for_completion', true)
            ->where('section_progress.status', 'completed')
            ->count();

        // Calculate new progress (avoid divide by zero)
        $progress = $totalRequiredSections > 0
            ? ($completedSections / $totalRequiredSections) * 100
            : 0;

        // Update lesson_student progress
        DB::table('lesson_student')
            ->where('lesson_id', $lessonId)
            ->where('idnumber', $request->idnumber)
            ->update([
                'progress' => $progress,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Section progress updated',
            'lesson_progress' => $progress
        ]);
    }

    public function submitQuizAnswer(Request $request, $quizAssessmentId)
    {
        $user = Auth::user();

        if (!$user || $user->usertype !== 'Student') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'answer_text' => 'nullable|string',
            'file' => 'nullable|file|max:5120', // Max 5MB
        ]);

        $studentId = $user->idnumber;

        $quiz = QuizAssessment::findOrFail($quizAssessmentId);

        // Ensure the student is linked to this quiz
        $existing = DB::table('quiz_assessment_student')
            ->where('quiz_assessment_id', $quiz->id)
            ->where('student_idnumber', $studentId)
            ->first();

        if (!$existing) {
            return response()->json(['error' => 'Not linked to this quiz.'], 403);
        }

        // Check attempt limit
        if ($existing->attempts >= $quiz->max_attempts) {
            return response()->json([
                'error' => 'Maximum number of attempts reached.'
            ], 403);
        }

        // Upload file to Firebase if provided
        $fileUrl = $existing->file_path ?? null;

        if ($request->hasFile('file')) {
            $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
            $bucket = $firebase->createStorage()->getBucket();

            $file = $request->file('file');
            $firebaseFilePath = 'quiz_submissions/' . uniqid() . '_' . $file->getClientOriginalName();

            $bucket->upload(
                fopen($file->getRealPath(), 'r'),
                ['name' => $firebaseFilePath]
            );

            $fileUrl = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";
        }

        // Update submission
        DB::table('quiz_assessment_student')
            ->where('quiz_assessment_id', $quiz->id)
            ->where('student_idnumber', $studentId)
            ->update([
                'answer_text' => $request->input('answer_text'),
                'file_path' => $fileUrl,
                'submitted_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        $this->updateSectionAndLessonProgress($quiz->section_id, $user->idnumber);

        return response()->json(['message' => 'Quiz answer submitted successfully.']);
    }

    public function checkQuizAnswers($quizAssessmentId)
    {
        $user = Auth::user();

        // Ensure the user is a teacher
        if (!$user || $user->usertype !== 'Teacher') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // Get the quiz
        $quiz = QuizAssessment::findOrFail($quizAssessmentId);

        // Fetch all submissions
        $submissions = DB::table('quiz_assessment_student')
            ->where('quiz_assessment_id', $quiz->id)
            ->join('students', 'quiz_assessment_student.student_idnumber', '=', 'students.idnumber')
            ->select(
                'students.idnumber',
                'students.firstname',
                'students.lastname',
                'quiz_assessment_student.answer_text',
                'quiz_assessment_student.file_path',
                'quiz_assessment_student.submitted_at',
                'quiz_assessment_student.attempts',
                'quiz_assessment_student.score'
            )
            ->orderByDesc('quiz_assessment_student.submitted_at')
            ->get();

        return response()->json([
            'quiz_id' => $quiz->id,
            'quiz_title' => $quiz->title,
            'submissions' => $submissions,
        ]);
    }

    public function gradeStudentQuiz(Request $request, $quizAssessmentId, $studentIdnumber)
    {
        $user = Auth::user();

        // Ensure the user is a teacher
        if (!$user || $user->usertype !== 'Teacher') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // Validate score input
        $request->validate([
            'score' => 'required|integer|min:0',
        ]);

        // Ensure quiz exists
        $quiz = QuizAssessment::with('section.lesson.class')->findOrFail($quizAssessmentId);

        // Get the class from quiz's section > lesson > class
        $classId = optional($quiz->section->lesson->class)->class_id;

        if (!$classId) {
            return response()->json(['error' => 'Quiz is not linked to any class.'], 400);
        }

        // Check if teacher is enrolled in the class
        $isAssigned = DB::table('class_teachers')
            ->where('class_id', $classId)
            ->where('idnumber', $user->idnumber)
            ->exists();

        if (!$isAssigned) {
            return response()->json(['error' => 'You are not assigned to this class.'], 403);
        }

        // Check if student has a submission
        $existing = DB::table('quiz_assessment_student')
            ->where('quiz_assessment_id', $quiz->id)
            ->where('student_idnumber', $studentIdnumber)
            ->first();

        if (!$existing) {
            return response()->json(['error' => 'Student has no submission for this quiz.'], 404);
        }

        // Update the score
        DB::table('quiz_assessment_student')
            ->where('quiz_assessment_id', $quiz->id)
            ->where('student_idnumber', $studentIdnumber)
            ->update([
                'score' => $request->input('score'),
                'updated_at' => now(),
            ]);

        // ✅ FIXED LINE HERE
        $this->updateSectionAndLessonProgress($quiz->section_id, $studentIdnumber);

        return response()->json(['message' => 'Score updated successfully.']);
    }

    private function updateSectionAndLessonProgress($sectionId, $studentIdnumber)
    {
        $section = DB::table('sections')->where('id', $sectionId)->first();

        if (!$section) return;

        // Update or insert section progress
        DB::table('section_progress')->updateOrInsert(
            [
                'section_id' => $section->id,
                'idnumber' => $studentIdnumber,
            ],
            [
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Recalculate lesson progress
        $lessonId = $section->lesson_id;

        $totalRequired = DB::table('sections')
            ->where('lesson_id', $lessonId)
            ->where('require_for_completion', true)
            ->count();

        $completed = DB::table('section_progress')
            ->join('sections', 'sections.id', '=', 'section_progress.section_id')
            ->where('sections.lesson_id', $lessonId)
            ->where('section_progress.idnumber', $studentIdnumber)
            ->where('sections.require_for_completion', true)
            ->where('section_progress.status', 'completed')
            ->count();

        $progress = $totalRequired > 0 ? ($completed / $totalRequired) * 100 : 0;

        DB::table('lesson_student')
            ->where('lesson_id', $lessonId)
            ->where('idnumber', $studentIdnumber)
            ->update([
                'progress' => $progress,
                'updated_at' => now(),
            ]);
    }

}
