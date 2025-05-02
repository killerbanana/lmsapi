<?php

namespace App\Http\Controllers;

use App\Models\StudentSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Subject;


class StudentSubjectController extends Controller
{
    public function assignStudentToSubject(Request $request)
    {
        $user = Auth::user();

        // Validate input
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|string',
            'idnumber' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $subjectAsignedToTeacher = StudentSubject::where('idnumber', $user->idnumber)
            ->where('subject_id', $request->subject_id)
            ->where('usertype', $user->usertype)
            ->first();

        if (!$subjectAsignedToTeacher) {
            return response()->json(['message' => 'This subject is not asigned to you.'], 404);
        }
        

        // Get teacher by idnumber
        $student = User::where('idnumber', $request->idnumber)
                        ->where('usertype', 'Student')
                        ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found or invalid role.'], 404);
        }

        // Find the subject
        $subject = Subject::where('subject_id', $request->subject_id)->first();
        if (!$subject) {
            return response()->json(['message' => 'Subject not found.'], 404);
        }

        $existingAssociation = StudentSubject::where('idnumber', $request->idnumber)
            ->where('subject_id', $subject->subject_id)
            ->where('usertype', $student->usertype)
            ->first();

        if ($existingAssociation) {
            return response()->json([
                'message' => 'This user is already associated with this subject as ' . $existingAssociation->usertype . '.',
            ], 409);
        }

        $studentSubject = StudentSubject::create([
            'idnumber' => $request->idnumber,
            'subject_id' => $subject->subject_id,
            'usertype' => $student->usertype,
        ]);

        return response()->json(['message' => 'Student assigned to subject successfully.'], 200);
    }

    public function assignTeacherToSubject(Request $request)
    {
        $user = Auth::user();

        // Validate input
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|string',
            'idnumber' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Get teacher by idnumber
        $teacher = User::where('idnumber', $request->idnumber)
                        ->where('usertype', 'Teacher')
                        ->first();

        if (!$teacher) {
            return response()->json(['message' => 'Teacher not found or invalid role.'], 404);
        }

        // Find the subject
        $subject = Subject::where('subject_id', $request->subject_id)->first();
        if (!$subject) {
            return response()->json(['message' => 'Subject not found.'], 404);
        }

        $existingAssociation = StudentSubject::where('idnumber', $request->idnumber)
            ->where('subject_id', $subject->subject_id)
            ->where('usertype', $teacher->usertype)
            ->first();

        if ($existingAssociation) {
            return response()->json([
                'message' => 'This user is already associated with this subject as ' . $existingAssociation->usertype . '.',        
            ], 409);
        }

        $studentSubject = StudentSubject::create([
            'idnumber' => $request->idnumber,
            'subject_id' => $subject->subject_id,
            'usertype' => $teacher->usertype,
        ]);

        return response()->json(['message' => 'Teacher assigned to subject successfully.'], 200);
    }
}

