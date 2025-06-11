<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lesson;
use App\Models\LessonStudent;
use App\Models\StudentClass;
use Illuminate\Support\Facades\Validator;

class LessonStudentController extends Controller
{
    /**
     * Manually assign a student to a lesson.
     */
    public function create(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required|exists:lessons,id',
            'idnumber' => 'required|string|exists:users,idnumber',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validated = $validator->validated();

        // Check if the student is already assigned
        $exists = LessonStudent::where('lesson_id', $validated['lesson_id'])
            ->where('idnumber', $validated['idnumber'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Student already assigned to this lesson.'], 409);
        }

        // Assign student
        $record = LessonStudent::create([
            'lesson_id' => $validated['lesson_id'],
            'idnumber' => $validated['idnumber'],
        ]);

        return response()->json([
            'message' => 'Student assigned to lesson successfully.',
            'assignment' => $record
        ], 201);
    }
}
