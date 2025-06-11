<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\ContentSection;
use App\Models\SectionResource;
use App\Models\SectionQuiz;
use Kreait\Firebase\Factory;

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

        // Handle quiz link for assessment
        if ($validated['type'] === 'assessment' && $validated['subtype'] === 'quiz') {
            SectionQuiz::create([
                'section_id' => $section->id,
                'quiz_id' => $validated['quiz_id'],
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

        return response()->json(['message' => 'Section created successfully'], 201);
    }

    /**
     * Get all sections linked to lessons taught by a teacher (via class).
     */
    public function getAllSection(Request $request)
    {
        $teacherIdnumber = $request->query('idnumber');
        $name = $request->query('name');

        $query = Section::with(['resources', 'completionActions', 'lesson'])
            ->whereHas('lesson', function ($q) use ($teacherIdnumber) {
                $q->whereIn('class_id', function ($subQuery) use ($teacherIdnumber) {
                    $subQuery->select('class_id')
                        ->from('class_teachers')
                        ->where('idnumber', $teacherIdnumber);
                });
            });

        if ($name) {
            $query->where('title', 'like', "%$name%");
        }

        return response()->json($query->get());
    }
}
