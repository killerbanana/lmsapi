<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DropboxAssessmentController extends Controller
{
    public function createDropBox(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => 'required|integer|exists:lessons,id',
            'title' => 'nullable|string',
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
}
