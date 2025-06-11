<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\RoleAbilitiesService;
use App\Models\Teachers;
use App\Models\Classes;
use App\Models\TeacherClass;
use App\Models\StudentClass;
use Illuminate\Support\Facades\Auth;

class TeacherClassController extends Controller
{
    public function assignTeacherToClass(Request $request)
    {
        $validated = $request->validate([
            'idnumber' => 'required|string|max:255',
            'class_id' => 'required|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        $teacher_exists = Teachers::where('idnumber', $validated['idnumber'])->exists();
        if (!$teacher_exists) {
            return response()->json(['message' => 'Teacher does not exist.'], 404);
        }

        $class_exists = Classes::where('class_id', $validated['class_id'])->exists();
        if (!$class_exists) {
            return response()->json(['message' => 'Class does not exist.'], 404);
        }

        $exists = TeacherClass::where('idnumber', $validated['idnumber'])
                              ->where('class_id', $validated['class_id'])
                              ->exists();

        if ($exists) {
            return response()->json(['message' => 'Teacher already added in this class.'], 409);
        }

        // // Add student to class
        $teacherClass = TeacherClass::create($validated);

        return response()->json([
            'message' => 'Teacher successfully added to class.',
            'data' => $teacherClass
        ], 201);
    }

    public function getAllClass(Request $request)
    {
        $user = Auth::user();

        $perPage = $request->query('perPage', 10); // default 10 per page
        $searchClassId = $request->query('class_id');
        $searchClassName = $request->query('class_name');

        if ($user->usertype === 'Administrator') {
            // Admin sees all classes
            $query = Classes::query();

            if ($searchClassId) {
                $query->where('class_id', $searchClassId);
            }

            if ($searchClassName) {
                $query->where('class_name', 'LIKE', '%' . $searchClassName . '%');
            }

            $paginated = $query->paginate($perPage);

            return response()->json([
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'classes' => $paginated->items(),
            ], 200);
        }

        if ($user->usertype === 'Student') {
            $query = StudentClass::where('idnumber', $user->idnumber)->with('class');

            if ($searchClassId) {
                $query->whereHas('class', function ($q) use ($searchClassId) {
                    $q->where('class_id', $searchClassId);
                });
            }

            if ($searchClassName) {
                $query->whereHas('class', function ($q) use ($searchClassName) {
                    $q->where('class_name', 'LIKE', '%' . $searchClassName . '%');
                });
            }

            $paginated = $query->paginate($perPage);

            return response()->json([
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'classes' => $paginated->items(),
            ], 200);
        }

        if ($user->usertype === 'Teacher') {
            $query = TeacherClass::where('idnumber', $user->idnumber)->with('class');

            if ($searchClassId) {
                $query->whereHas('class', function ($q) use ($searchClassId) {
                    $q->where('class_id', $searchClassId);
                });
            }

            if ($searchClassName) {
                $query->whereHas('class', function ($q) use ($searchClassName) {
                    $q->where('class_name', 'LIKE', '%' . $searchClassName . '%');
                });
            }

            $paginated = $query->paginate($perPage);

            return response()->json([
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'classes' => $paginated->items(),
            ], 200);
        }

        return response()->json(['message' => 'User type not supported.'], 403);
    }
}
