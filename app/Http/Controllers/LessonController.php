<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Validator;
use App\Services\RoleAbilitiesService;
use Illuminate\Http\Request;
use App\Models\Lessons;
use App\Models\Classes;
use App\Models\TeacherClass;
use Illuminate\Support\Facades\Auth;


class LessonController extends Controller
{
    public function createLesson(Request $request)
    {
        $user = Auth::user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'class_id' => 'required|string|max:255',
        ]);

        // If validation fails, return the errors
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Retrieve validated data
        $validated = $validator->validated();

        $teacher_exists = TeacherClass::where('class_id', $validated['class_id'])->where('idnumber', $user->idnumber )->exists();

        if (!$teacher_exists) {
            return response()->json(['message' => 'You are not enrolled in this class. Cannot create a lesson'], 404);
        }

        $class_exists = Classes::where('class_id', $validated['class_id'])->exists();
        if (!$class_exists) {
            return response()->json(['message' => 'Class does not exist.'], 404);
        }

        // Create the new subject
        $lesson = Lessons::create([
            'name' => $validated['name'],
            'class_id' => $validated['class_id'],
            'idnumber' => $user->idnumber 
        ]);

        // Return a JSON response
        return response()->json(['message' => 'Lesson created successfully', 'lesson' => $lesson], 201);
    }

    public function getAllLessons(Request $request)
    {
        try {
            $classId = $request->query('classId');
            $perPage = $request->query('perPage', 10); // default to 10 per page
            $user = Auth::user();

            // Build base query
            $query = Lessons::query();

            if ($user->usertype !== 'Administrator') {
                $query->where('idnumber', $user->idnumber);
            }

            if ($classId) {
                $query->where('class_id', $classId);
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
}
