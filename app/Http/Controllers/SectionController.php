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
use Illuminate\Support\Facades\Auth;

class SectionController extends Controller
{
    /**
     * Create a new section for a lesson.
     */
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
        ]);

        $userIdnumber = auth()->user()->idnumber;

        // Check authorization
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

        if ($validated['type'] === 'assessment' && $validated['subtype'] === 'quiz') {
            $quiz = QuizAssessment::create([
                'section_id' => $section->id,
                'title' => $validated['title'] ?? 'Untitled Quiz',
                'instructions' => null,
                'points' => 100,
                'category' => null,
                'start' => now(),
                'due' => now()->addDays(7),
                'grading_scale' => 'Default',
                'grading' => 'Normal',
                'max_attempts' => 1,
                'allow_late' => false,
                'timed' => false,
                'instant_feedback' => false,
                'release_grades' => 'Instant',
                'grading_method' => 'latest',
                'disable_past_due' => false,
                'autocomplete_on_retake' => false,
                'randomize_order' => true,
                'allow_review' => true,
                'allow_jump' => true,
                'show_in_results' => [],
                'library' => 'Personal',
            ]);
        }

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


    public function getAllSection(Request $request)
    {
        $user = Auth::user();
        $classId = $request->query('class_id');

        if (!in_array($user->usertype, ['Administrator', 'Teacher'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $lessonsQuery = \App\Models\Lesson::with('class');

        // Filter lessons by the teacher's classes
        if ($user->usertype === 'Teacher') {
            $lessonsQuery->whereIn('class_id', function ($q) use ($user) {
                $q->select('class_id')
                ->from('class_teachers')
                ->where('idnumber', $user->idnumber);
            });
        }

        // Filter by specific class if provided
        if ($classId) {
            $lessonsQuery->where('class_id', $classId);
        }

        // Get all relevant lessons
        $lessons = $lessonsQuery->get();
        $lessonIds = $lessons->pluck('id')->toArray();

        // Get all related sections with eager-loaded relations
        $sections = \App\Models\Section::with(['resources', 'completionActions', 'lesson'])
            ->whereIn('lesson_id', $lessonIds)
            ->get();

        // Group sections by lesson
        $grouped = $lessons->map(function ($lesson) use ($sections) {
            return [
                'lesson' => $lesson,
                'sections' => $sections->where('lesson_id', $lesson->id)->map(function ($section) {
                    if ($section->type === 'content') {
                        $section->load('contentSections');
                    }
                    if ($section->type === 'assessment') {
                        $section->load('assessmentSection');
                    }


                    return $section;
                })->values()
            ];
        })->filter(fn ($item) => $item['sections']->isNotEmpty())->values();

        return response()->json([
            'message' => 'Sections grouped by lesson',
            'data' => $grouped
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
}
