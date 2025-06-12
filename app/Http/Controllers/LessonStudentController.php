<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lesson;
use App\Models\LessonStudent;
use App\Models\StudentClass;
use Illuminate\Support\Facades\DB;
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

    public function updateLessonProgress(Request $request)
    {
        $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'idnumber' => 'required|exists:users,idnumber', // student idnumber
            'progress' => 'required|numeric|min:0|max:100',
        ]);

        $user = $request->user(); // Authenticated user
        $lesson = Lesson::findOrFail($request->lesson_id);

        // Check if the student is assigned to the lesson
        $isAssignedStudent = DB::table('lesson_student')
            ->where('lesson_id', $request->lesson_id)
            ->where('idnumber', $request->idnumber)
            ->exists();


        if (!$isAssignedStudent) {
            return response()->json(['message' => 'Student not assigned to this lesson'], 403);
        }

        // Check if user is the student updating their own progress
        $isStudent = $user->usertype === 'Student' && $user->idnumber === $request->idnumber;

        // Check if user is a teacher assigned to the class of the lesson
        $isTeacher = $user->usertype === 'Teacher' &&
            DB::table('class_teachers')
                ->where('class_id', $lesson->class_id)
                ->where('idnumber', $user->idnumber)
                ->where('status', 'active')
                ->exists();

        // Only allow if student updating self or assigned teacher
        if (!$isStudent && !$isTeacher) {
            return response()->json(['message' =>  $user->idnumber], 403);
        }

        // Update progress
        DB::table('lesson_student')
            ->where('lesson_id', $request->lesson_id)
            ->where('idnumber', $request->idnumber)
            ->update([
                'progress' => $request->progress,
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Progress updated successfully']);
    }
}
