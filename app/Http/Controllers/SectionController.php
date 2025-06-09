<?php

namespace App\Http\Controllers;

use Kreait\Firebase\Factory;
use Illuminate\Http\Request;
use App\Models\Lessons;
use App\Models\Section;
use App\Models\ContentSection;

use App\Models\SectionResource;

class SectionController extends Controller
{
    public function index(Lesson $lesson)
    {
        return response()->json(
            $lesson->sections()->with('resources', 'completionActions')->get()
        );
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => 'required|integer|exists:lessons,id',
            'title' => 'required|string|max:255',
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

        // Authorization: Check that user owns the lesson
        $userIdnumber = auth()->user()->idnumber;
        $lesson = Lessons::where('id', $validated['lesson_id'])
            ->where('idnumber', $userIdnumber)
            ->first();

        if (!$lesson) {
            abort(403, 'You are not authorized to create a section for this lesson.');
        }

        // Create Section
        $section = Section::create([
            'lesson_id' => $validated['lesson_id'],
            'type' => $validated['type'],
            'subtype' => $validated['subtype'],
            'require_for_completion' => $validated['require_for_completion'] ?? false,
        ]);

        // Handle content (page subtype)
        if ($validated['type'] === 'content' && $validated['subtype'] === 'page') {
            ContentSection::create([
                'section_id' => $section->id,
                'content' => $validated['page_content'] ?? '',
            ]);
        }

        // Handle assessment (quiz subtype)
        if ($validated['type'] === 'assessment' && $validated['subtype'] === 'quiz') {
            SectionQuiz::create([
                'section_id' => $section->id,
                'quiz_id' => $validated['quiz_id'],
            ]);
        }

        // Upload Resources to Firebase Storage
        if ($request->hasFile('files')) {
            $files = $request->file('files');

            try {
                $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
                $bucket = $firebase->createStorage()->getBucket();
            } catch (\Exception $e) {
                \Log::error("Firebase initialization failed: " . $e->getMessage());
                return response()->json(['message' => 'Failed to initialize Firebase storage.'], 500);
            }

            foreach ($files as $index => $file) {
                $name = $validated['resource_names'][$index] ?? 'Unnamed Resource';
                $type = $validated['resource_types'][$index] ?? 'pdf';

                $firebaseFilePath = 'sections/' . uniqid() . '_' . $file->getClientOriginalName();

                try {
                    $bucket->upload(
                        fopen($file->getRealPath(), 'r'),
                        ['name' => $firebaseFilePath]
                    );

                    $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";

                    $section->resources()->create([
                        'name' => $name,
                        'url' => $url,
                        'type' => $type,
                    ]);
                } catch (\Exception $e) {
                    \Log::error("Failed to upload or save resource file: " . $e->getMessage());
                    return response()->json(['message' => 'Failed to upload resource file.'], 500);
                }
            }
        }

        // Save Completion Actions
        // if (!empty($validated['completion_actions'])) {
        //     foreach ($validated['completion_actions'] as $action) {
        //         $section->completionActions()->create([
        //             'action_type' => $action['action_type'],
        //             'parameters' => $action['parameters'] ?? [],
        //         ]);
        //     }
        // }

        // return response()->json([
        //     'message' => 'Section created successfully.',
        //     'data' => $section->load('resources', 'completionActions'),
        // ], 201);
    }


    public function indexAll(Request $request)
    {
        $user = auth()->user();
        $userIdnumber = $user->idnumber;
        $userType = $user->usertype;
        $perPage = max(1, min((int) $request->get('per_page', 10), 100));
        $lessonId = $request->get('lesson_id'); // optional filter

        $query = Section::with('resources', 'completionActions');

        $query->whereHas('lesson', function ($q) use ($userType, $userIdnumber, $lessonId) {
            if ($userType === 'Instructor') {
                $q->where('idnumber', $userIdnumber);
            } elseif ($userType === 'Student') {
                $classIds = \App\Models\StudentClass::where('idnumber', $userIdnumber)
                            ->pluck('class_id');
                $q->whereIn('class_id', $classIds);
            }

            if ($lessonId) {
                $q->where('id', $lessonId);
            }
        });

        $sections = $query->paginate($perPage);

        return response()->json($sections);
    }

}
