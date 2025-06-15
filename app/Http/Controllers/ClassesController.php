<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Models\Students;
use App\Models\Teachers;
use Illuminate\Support\Facades\Hash;
use App\Services\RoleAbilitiesService;
use App\Models\Classes;
use Illuminate\Support\Facades\Auth;
use App\Models\TeacherClass;
use App\Models\StudentClass;
use Kreait\Firebase\Factory;

class ClassesController extends Controller
{
    public function getAllClass(Request $request)
    {
        $user = Auth::user();

        $perPage = $request->query('perPage', 10);
        $type = $request->query('type', 'student'); // default to 'student'
        $searchClassId = $request->query('class_id');
        $searchClassName = $request->query('class_name');

        // Allow only these user types
        if (!in_array($user->usertype, ['Administrator', 'Student', 'Teacher'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($type === 'student') {
            $query = StudentClass::query()->with('class');

            if (!in_array($user->usertype, ['Administrator', 'Student', 'Teacher'])) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            // Only own classes if Student
            if ($user->usertype === 'Student') {
                $query->where('idnumber', $user->idnumber);
            }

            // Admin can view all student classes
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

        if ($type === 'teacher') {
            $query = TeacherClass::query()->with('class');
            
            if (!in_array($user->usertype, ['Administrator', 'Teacher'])) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            // Only own classes if Teacher
            if ($user->usertype === 'Teacher') {
                $query->where('idnumber', $user->idnumber);
            }

            // Admin can view all teacher classes
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

        return response()->json(['message' => 'Invalid type. Must be student or teacher.'], 400);
    }

    public function createClass(Request $request)
    {
        $url = null;

        $validator = Validator::make($request->all(), [
            'class_id' => 'required|unique:classes,class_id',
            'class_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tag' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,enrolled,inprogress',
        ]);


        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');

            $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
            $bucket = $firebase->createStorage()->getBucket();

            $firebaseFilePath = 'users/photo_' . uniqid() . '_' . $file->getClientOriginalName();

            $bucket->upload(
                fopen($file->getRealPath(), 'r'),
                ['name' => $firebaseFilePath]
            );

            $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";
        }

        $class = Classes::create([
            'class_id' => $request->class_id,
            'class_name' => $request->class_name,
            'description' => $request->description,
            'tag' => $request->tag,
            'photo' => $url,
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Class added successfully',
            'data' => $class
        ], 201);
    }

    public function updateClass(Request $request, $id)
    {
        try
        {
            $validator = Validator::make($request->all(), [
            'class_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tag' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $class = Classes::where('class_id', $id)->first();

            if (!$class) {
                return response()->json(['message' => 'Class not found'], 404);
            }

            $data = [
                'class_name' => $request->class_name,
                'description' => $request->description,
                 'tag' => $request->tag,
                 'status' => $request->status,
            ];

            if ($request->hasFile('photo')) {
                    $file = $request->file('photo');

                    $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
                    $bucket = $firebase->createStorage()->getBucket();

                    $firebaseFilePath = 'users/photo_' . uniqid() . '_' . $file->getClientOriginalName();

                    $bucket->upload(
                        fopen($file->getRealPath(), 'r'),
                        ['name' => $firebaseFilePath]
                    );

                    $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";
                    $data['photo'] = $url; // only set photo if uploaded
                }

            $updated = Classes::where('class_id', $id)->update($data);

            return response()->json([
                'message' => 'Class updated successfully',
            ], 200);
        
        } catch (\Exception $e) {
            return response()->json(['error' => 'Deletion failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function gradeStudent(Request $request)
    {
        $user = Auth::user();

        // Ensure the user is a teacher
        if (!$user || $user->usertype !== 'Teacher') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'idnumber' => 'required|string|exists:users,idnumber', // student
            'class_id' => 'required|string|exists:classes,class_id',
            'grade' => 'required|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Check if the teacher is assigned to the class
        $isAssigned = \App\Models\TeacherClass::where('idnumber', $user->idnumber)
            ->where('class_id', $request->class_id)
            ->exists();

        if (!$isAssigned) {
            return response()->json(['message' => 'You are not assigned to this class.'], 403);
        }

        // Check if the student is enrolled in the class
        $classStudent = \App\Models\StudentClass::where('idnumber', $request->idnumber)
            ->where('class_id', $request->class_id)
            ->first();

        if (!$classStudent) {
            return response()->json(['message' => 'Student not enrolled in this class'], 404);
        }

        // Update the grade
        $classStudent->grade = $request->grade;
        $classStudent->save();

        return response()->json(['message' => 'Student graded successfully.'], 200);
    }

    public function deleteClass($id)
    {
        try {
            $class = Classes::where('class_id', $id)->first();

            if (!$class) {
                return response()->json(['message' => 'Class not found'], 404);
            }

            $class->delete();

            return response()->json(['message' => 'Class deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Deletion failed', 'details' => $e->getMessage()], 500);
        }
    }


}
