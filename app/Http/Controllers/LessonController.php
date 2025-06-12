<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Lesson; // ✅ FIXED model import
use App\Models\Classes;
use App\Models\TeacherClass;
use App\Models\StudentClass;
use App\Models\LessonStudent;

use Exception;

class LessonController extends Controller
{
     public function createLesson(Request $request)
    {
        $user = Auth::user();

        // Validate request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'class_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validated = $validator->validated();

        // Check if the teacher is assigned to the class
        $teacher_exists = TeacherClass::where('class_id', $validated['class_id'])
            ->where('idnumber', $user->idnumber)
            ->exists();

        if (!$teacher_exists) {
            return response()->json([
                'message' => 'You are not enrolled in this class. Cannot create a lesson.'
            ], 403);
        }

        // Ensure class exists
        $class_exists = Classes::where('class_id', $validated['class_id'])->exists();
        if (!$class_exists) {
            return response()->json(['message' => 'Class does not exist.'], 404);
        }

        // Create the lesson
        $lesson = Lesson::create([
            'name' => $validated['name'],
            'class_id' => $validated['class_id'],
            'idnumber' => $user->idnumber,
        ]);

        // Assign all students in the class to this lesson
        $students = StudentClass::where('class_id', $validated['class_id'])->pluck('idnumber');
        foreach ($students as $studentIdnumber) {
            LessonStudent::create([
                'lesson_id' => $lesson->id,
                'idnumber' => $studentIdnumber,
            ]);
        }

        return response()->json([
            'message' => 'Lesson created successfully and students assigned.',
            'lesson' => $lesson
        ], 201);
    }

    public function assignStudentToLessons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|string|max:255',
            'idnumber' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validated = $validator->validated();

        $class_id = $request->input('class_id');
        $studentIdnumber = $request->input('idnumber');

        // ✅ Check if the student is enrolled in the class
       $exists = StudentClass::where('idnumber', $studentIdnumber)
                              ->where('class_id', $class_id)
                              ->exists();


        if (!$exists) {
            // DEBUGGING
            $record = StudentClass::where('class_id', $class_id)->get();
            \Log::info('Class enrolled students', $record->toArray());

            return response()->json([
                'message' => 'Student is not enrolled in this class. Cannot assign to lessons.',
                'student' => $studentIdnumber,
                'class_id' => $class_id
            ], 404);
        }

        $lessons = Lesson::where('class_id', $class_id)->get();

        foreach ($lessons as $lesson) {
            $alreadyAssigned = LessonStudent::where('lesson_id', $lesson->id)
                ->where('idnumber', $studentIdnumber)
                ->exists();

            if (!$alreadyAssigned) {
                LessonStudent::create([
                    'lesson_id' => $lesson->id,
                    'idnumber' => $studentIdnumber,
                ]);
            }
        }

        return response()->json([
            'message' => 'Student assigned to existing lessons.',
            'student' => $studentIdnumber,
            'class_id' => $class_id
        ], 200);
    }

    

    public function getAllLessons(Request $request)
    {
        try {
            $classId = $request->query('classId');
            $perPage = $request->query('perPage', 10);
            $user = Auth::user();

            $query = Lesson::query();

            if ($user->usertype === 'Administrator') {
                if ($classId) {
                    $query->where('class_id', $classId);
                }
            } elseif ($user->usertype === 'Teacher') {
                $query->where('idnumber', $user->idnumber);
                if ($classId) {
                    $query->where('class_id', $classId);
                }
            } elseif ($user->usertype === 'Student') {
                $classIds = StudentClass::where('idnumber', $user->idnumber)->pluck('class_id');
                $query->whereIn('class_id', $classIds);
                if ($classId) {
                    $query->where('class_id', $classId);
                }
            }

            $paginated = $query->paginate($perPage);

            return response()->json([
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'lessons' => $paginated->items(),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching lessons.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function updateLesson(Request $request, $id)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'class_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validated = $validator->validated();

        $lesson = Lesson::find($id);
        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found.'], 404);
        }

        if ($lesson->idnumber !== $user->idnumber) {
            return response()->json(['message' => 'Unauthorized to update this lesson.'], 403);
        }

        $teacher_exists = TeacherClass::where('class_id', $validated['class_id'])
            ->where('idnumber', $user->idnumber)
            ->exists();

        if (!$teacher_exists) {
            return response()->json(['message' => 'You are not enrolled in this class. Cannot update the lesson.'], 403);
        }

        $lesson->update([
            'name' => $validated['name'],
            'class_id' => $validated['class_id']
        ]);

        return response()->json(['message' => 'Lesson updated successfully.', 'lesson' => $lesson], 200);
    }

    public function deleteLesson($id)
    {
        $user = Auth::user();

        $lesson = Lesson::find($id);
        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found.'], 404);
        }

        if ($lesson->idnumber !== $user->idnumber) {
            return response()->json(['message' => 'Unauthorized to delete this lesson.'], 403);
        }

        $lesson->delete();

        return response()->json(['message' => 'Lesson deleted successfully.'], 200);
    }
}
