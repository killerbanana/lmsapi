<?php

namespace App\Http\Controllers;

use App\Models\StudentSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentSubjectController extends Controller
{
    public function createStudentSubject(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'idnumber' => 'required|string|exists:users,idnumber',
            'subject_id' => 'required|string|exists:subjects,subject_id',
            'user_type' => 'required|in:Student,Teacher'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

         // Check if the combination of idnumber and subject_id already exists
         $existingAssociation = StudentSubject::where('idnumber', $request->idnumber)
         ->where('subject_id', $request->subject_id)
         ->first();

        if ($existingAssociation) {
            return response()->json([
                'message' => 'This student is already associated with this subject.',
                'existing_association' => $existingAssociation
            ], 409);  // 409 Conflict response
        }

        // Create the new student-subject record
        $studentSubject = StudentSubject::create([
            'idnumber' => $request->idnumber,
            'subject_id' => $request->subject_id,
            'user_type' => $request->user_type,
        ]);

        // Return a JSON response
        return response()->json([
            'message' => 'Student-Subject association created successfully',
            'student_subject' => $studentSubject,
        ], 201);
    }
}
