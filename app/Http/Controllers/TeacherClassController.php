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
            'idnumber' => 'required|string|exists:teachers,idnumber',
            'class_id' => 'required|string|exists:classes,class_id',
            'status' => 'nullable|in:active,inactive',
        ]);

        // Check if the class already has a teacher assigned
        $existingAssignment = TeacherClass::where('class_id', $validated['class_id'])->first();

        if ($existingAssignment) {
            return response()->json(['message' => 'A teacher is already assigned to this class. Only one teacher per class is allowed.'], 409);
        }

        // Add teacher to class
        $teacherClass = TeacherClass::create($validated);

        return response()->json([
            'message' => 'Teacher successfully assigned to class.',
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
