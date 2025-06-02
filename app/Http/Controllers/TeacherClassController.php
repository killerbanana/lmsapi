<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\RoleAbilitiesService;
use App\Models\Teachers;
use App\Models\Classes;
use App\Models\TeacherClass;
use Illuminate\Support\Facades\Auth;

class TeacherClassController extends Controller
{
    public function assignTeacherToClass(Request $request)
    {
        $validated = $request->validate([
            'idnumber' => 'required|string|max:255',
            'class_id' => 'required|string|max:255',
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

        if ($user->usertype === 'Administrator') {
            // Administrator sees all classes
            $lessons = TeacherClass::all();
        } else {
            // Others see only their own classes
            $lessons = TeacherClass::where('idnumber', $user->idnumber)->get();
        }

        return response()->json(['classes' => $lessons], 200);
    }

}
