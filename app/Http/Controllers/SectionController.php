<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lessons;
use App\Models\Section;
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
            'resources' => 'nullable|array',
            'resources.*.name' => 'required|string',
            'resources.*.url' => 'required|url',
            'resources.*.type' => 'nullable|string',
            'completion_actions' => 'nullable|array',
            'completion_actions.*.action_type' => 'required|string',
            'completion_actions.*.parameters' => 'nullable|array',
        ]);

        // ✅ Ensure current user owns the lesson
        $userIdnumber = auth()->user()->idnumber;
        $lesson = Lessons::where('id', $validated['lesson_id'])
                        ->where('idnumber', $userIdnumber)
                        ->first();

        if (!$lesson) {
            abort(403, 'You are not authorized to create a section for this lesson.');
        }

        // Create section
        $section = Section::create([
            'lesson_id' => $validated['lesson_id'],
            'title' => $validated['title'],
            'introduction' => $validated['introduction'] ?? null,
            'require_for_completion' => $validated['require_for_completion'] ?? false,
            'completion_time_estimate' => $validated['completion_time_estimate'] ?? null,
        ]);

        // Attach resources
        if (!empty($validated['resources'])) {
            foreach ($validated['resources'] as $res) {
                $section->resources()->create([
                    'name' => $res['name'],
                    'url' => $res['url'],
                    'type' => $res['type'] ?? 'pdf',
                ]);
            }
        }

        // Attach completion actions
        if (!empty($validated['completion_actions'])) {
            foreach ($validated['completion_actions'] as $action) {
                $section->completionActions()->create([
                    'action_type' => $action['action_type'],
                    'parameters' => $action['parameters'] ?? [],
                ]);
            }
        }

        return response()->json([
            'message' => 'Section added.',
            'data' => $section->load('resources', 'completionActions')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $section = Section::findOrFail($id);

        // Ensure the user owns the lesson this section belongs to
        $userIdnumber = auth()->user()->idnumber;
        if ($section->lesson->idnumber !== $userIdnumber) {
            abort(403, 'You are not authorized to update this section.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'introduction' => 'nullable|string',
            'require_for_completion' => 'boolean',
            'completion_time_estimate' => 'nullable|integer|min:1',
        ]);

        $section->update($validated);

        return response()->json([
            'message' => 'Section updated successfully.',
            'data' => $section
        ]);
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
