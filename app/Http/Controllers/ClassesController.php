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

        // Only allow Administrator
        if ($user->usertype !== 'Administrator') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $perPage = $request->query('perPage', 10); // default 10 per page
        $type = $request->query('type', 'student'); // default to student if not provided

        if ($type === 'student') {
            $query = StudentClass::query();
            $paginated = $query->paginate($perPage);

            return response()->json([
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'classes' => $paginated->items(),
            ], 200);
        } elseif ($type === 'teacher') {
            $query = TeacherClass::query();
            $paginated = $query->paginate($perPage);

            return response()->json([
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'classes' => $paginated->items(),
            ], 200);

        } else {
            return response()->json(['message' => 'Invalid type. Must be student or teacher.'], 400);
        }

        
    }

    public function createClass(Request $request)
    {
        $url = null;

        $validator = Validator::make($request->all(), [
            'class_id' => 'required|unique:classes,class_id',
            'class_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tag' => 'nullable|string',
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
            'photo' => $url
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
