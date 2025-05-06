<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\RoleAbilitiesService;
use App\Models\StudentClass;

class StudentClassController extends Controller
{
    public function assignStudentToClass(Request $request)
    {
        $validated = $request->validate([
            'idnumber' => 'required|string|max:255',
            'class_id' => 'required|string|max:255',
        ]);

        $student_exists = StudentClass::where('idnumber', $validated['idnumber'])->exists();
        if (!$student_exists) {
            return response()->json(['message' => 'Student does not exist.'], 404);
        }

        $class_exists = StudentClass::where('class_id', $validated['class_id'])->exists();
        if (!$class_exists) {
            return response()->json(['message' => 'Class does not exist.'], 404);
        }

        $exists = StudentClass::where('idnumber', $validated['idnumber'])
                              ->where('class_id', $validated['class_id'])
                              ->exists();

        if ($exists) {
            return response()->json(['message' => 'Student already enrolled in class.'], 409);
        }

        // // Add student to class
        $studentClass = StudentClass::create($validated);

        return response()->json([
            'message' => 'Student successfully added to class.',
            'data' => $studentClass
        ], 201);
    }
}
