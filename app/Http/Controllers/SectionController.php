<?php

namespace App\Http\Controllers;

use Kreait\Firebase\Factory;
use Illuminate\Http\Request;
use App\Models\Lessons;
use App\Models\Section;
use App\Models\ContentSection;
use App\Models\LessonStudent; 
use App\Models\SectionResource;

class SectionController extends Controller
{
    // Make sure this is at the top

    public function lesson()
    {
        return $this->belongsTo(Lessons::class, 'lesson_id');
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

        $userIdnumber = auth()->user()->idnumber;

        $lesson = Lessons::where('id', $validated['lesson_id'])
            ->where('idnumber', $userIdnumber)
            ->first();

        if (!$lesson) {
            abort(403, 'You are not authorized to create a section for this lesson.');
        }

        // Create base section
        $section = Section::create([
            'lesson_id' => $validated['lesson_id'],
            'type' => $validated['type'],
            'subtype' => $validated['subtype'],
            'require_for_completion' => $validated['require_for_completion'] ?? false,
        ]);

        // If content section, create content record
        if ($validated['type'] === 'content' && $validated['subtype'] === 'page') {
            ContentSection::create([
                'section_id' => $section->id,
                'introduction' => $validated['introduction'] ?? null,
                'content' => $validated['page_content'] ?? '',
            ]);
        }

        // If assessment section, create quiz link
        if ($validated['type'] === 'assessment' && $validated['subtype'] === 'quiz') {
            SectionQuiz::create([
                'section_id' => $section->id,
                'quiz_id' => $validated['quiz_id'],
            ]);
        }

        // Upload resources
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

        return response()->json(['message' => 'Section created successfully'], 201);
    }


    public function indexAll(Request $request)
    {
        $user = auth()->user();
        $userIdnumber = $user->idnumber;
        $userType = $user->usertype;
        $perPage = max(1, min((int) $request->get('per_page', 10), 100));
        $lessonId = $request->get('lesson_id'); // optional filter

        $query = Section::with(['resources', 'completionActions', 'lesson']);

        $query->whereHas('lesson', function ($q) use ($userType, $userIdnumber, $lessonId) {
            if ($userType === 'Teacher') {
                $q->where('idnumber', $userIdnumber); // Only lessons the instructor owns
            } elseif ($userType === 'Student') {
                $classIds = StudentClass::where('idnumber', $userIdnumber)->pluck('class_id');
                $q->whereIn('class_id', $classIds); // Lessons in student's classes
            }

            if ($lessonId) {
                $q->where('id', $lessonId); // Optional filter by specific lesson
            }
        });

        $sections = $query->paginate($perPage);

        return response()->json($sections);
    }

}
