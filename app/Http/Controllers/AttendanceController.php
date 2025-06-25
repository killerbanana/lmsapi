<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\StudentClass;
use App\Models\Classes; // Assuming you have a Classes model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display the attendance sheet for a specific class on a specific date.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // --- 1. Validation ---
        // The 'date' validation is changed from 'required' to 'sometimes'
        // to allow requests without a specific date.
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|string|exists:classes,class_id',
            'date' => 'sometimes|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => 'The provided data is not valid', 'details' => $validator->errors()], 422);
        }
        
        $classId = $request->input('class_id');
        $date = $request->input('date');

        // --- 2. Data Retrieval ---
        // The query is now conditional. If a date is provided, it filters by that date.
        // If no date is provided, it returns all attendance records for the class.
        $query = DB::table('class_students')
            ->join('users', 'class_students.idnumber', '=', 'users.idnumber')
            ->join('students', 'users.idnumber', '=', 'students.idnumber')
            ->join('attendances', 'class_students.id', '=', 'attendances.class_student_id')
            ->where('class_students.class_id', $classId)
            ->select(
                'attendances.id as attendance_id', // Added attendance ID
                'class_students.id as class_student_id',
                'users.idnumber',
                'students.firstname',
                'students.lastname',
                'attendances.status',
                'attendances.remarks',
                'attendances.attendance_date' // Added date to the selection
            );

        // Use when() to conditionally apply the date filter
        $studentsWithAttendance = $query->when($date, function ($q, $date) {
            return $q->where('attendances.attendance_date', $date);
        })->get();

        // --- 3. Response Formatting ---
        // The date is now included in each record.
        $attendanceData = $studentsWithAttendance->map(function ($student) {
            return [
                'attendance_id' => $student->attendance_id,
                'class_student_id' => $student->class_student_id,
                'idnumber' => $student->idnumber,
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'status' => $student->status,
                'remarks' => $student->remarks,
                'attendance_date' => $student->attendance_date,
            ];
        });

        // --- 4. Return JSON Response ---
        return response()->json([
            'class_id' => $classId,
            'attendance_date' => $date, // This will be the requested date, or null if all dates were fetched
            'attendance_sheet' => $attendanceData,
        ], 200);
    }

    /**
     * Get all attendance records for the currently authenticated student.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

     public function getStudentAttendance(Request $request)
    {
        // --- 1. Get Authenticated User ---
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // --- 2. Determine if User is a Parent and Get Student ID ---
        $studentIdNumber = null;
        $parent = DB::table('parent_tbl')->where('idnumber', $user->idnumber)->first();

        if ($parent && $parent->linked_id) {
            // User is a parent with a linked child
            $studentIdNumber = $parent->linked_id;
        } else {
            // User is assumed to be a student
            $studentIdNumber = $user->idnumber;
        }
        
        // --- 3. Validate Student Existence ---
        $studentDetails = DB::table('students')->where('idnumber', $studentIdNumber)->first();
        if (!$studentDetails) {
            return response()->json(['error' => 'No student record found for this user or linked child.'], 404);
        }

        // --- 4. Validation for Optional Filters ---
        $validator = Validator::make($request->all(), [
            'class_id' => 'sometimes|string|exists:classes,class_id',
            'date' => 'sometimes|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => 'The provided data is not valid', 'details' => $validator->errors()], 422);
        }

        // --- 5. Data Retrieval ---
        $classId = $request->input('class_id');
        $date = $request->input('date');
        
        $query = DB::table('attendances')
            ->join('class_students', 'attendances.class_student_id', '=', 'class_students.id')
            ->join('classes', 'class_students.class_id', '=', 'classes.class_id')
            ->where('class_students.idnumber', $studentIdNumber) // Filter by the determined student's idnumber
            ->select(
                'attendances.id as attendance_id', // Added attendance ID
                'classes.class_id',
                'classes.class_name',
                'attendances.attendance_date',
                'attendances.status',
                'attendances.remarks'
            )
            ->orderBy('attendances.attendance_date', 'desc');

        // Conditionally apply filters
        $records = $query->when($classId, function ($q) use ($classId) {
            return $q->where('classes.class_id', $classId);
        })->when($date, function ($q) use ($date) {
            return $q->where('attendances.attendance_date', $date);
        })->get();

        // --- 6. Format and Return JSON Response ---
        return response()->json([
            'student' => [
                'idnumber' => $studentDetails->idnumber,
                'firstname' => $studentDetails->firstname,
                'lastname' => $studentDetails->lastname,
            ],
            'attendance_records' => $records,
        ], 200);
    }
    // public function getStudentAttendance(Request $request)
    // {
    //     $user = Auth::user();

    //     // 1. Check if the authenticated user is a student
    //     if ($user->usertype !== 'Student') {
    //         return response()->json(['message' => 'This action is only for students.'], 403);
    //     }

    //     // 2. Get all attendance records for that student's enrollments.
    //     // This is a more direct way to query through relationships.
    //     $attendances = Attendance::whereHas('studentClass', function ($query) use ($user) {
    //             $query->where('idnumber', $user->idnumber);
    //         })
    //         ->with('studentClass:class_id') // Eager load class details
    //         ->get();

    //     // 3. Format the response
    //     $formattedAttendances = $attendances->map(function($record) {
    //         return [
    //             'class_id' => $record,
    //             // 'class_name' => $record->classStudent->class->class_name,
    //             'attendance_date' => $record->attendance_date->format('Y-m-d'),
    //             'status' => $record->status,
    //             'remarks' => $record->remarks
    //         ];
    //     });

    //     return response()->json($formattedAttendances, 200);
    // }

    /**
     * Store or update attendance for an entire class on a specific day.
     * This is a "bulk" operation, which is more practical for attendance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeClassAttendance(Request $request)
    {
        // --- Validation ---
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|string|exists:classes,class_id',
            'attendance_date' => 'required|date_format:Y-m-d',
            'attendances' => 'required|array',
            'attendances.*.class_student_id' => 'required|integer|exists:class_students,id',
            'attendances.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'attendances.*.remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $attendanceDate = $request->input('attendance_date');
        $attendancesInput = $request->input('attendances');

        // --- Database Operation ---
        foreach ($attendancesInput as $record) {
            // Use updateOrCreate to either create a new record or update an existing one
            // for the same student in the same class on the same day.
            Attendance::updateOrCreate(
                [
                    'class_student_id' => $record['class_student_id'],
                    'attendance_date'   => $attendanceDate,
                ],
                [
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                ]
            );
        }

        return response()->json(['message' => 'Attendance for class ' . $request->class_id . ' on ' . $attendanceDate . ' has been saved.'], 200);
    }
    
    /**
     * Display a specific attendance record.
     *
     * @param  \App\Models\Attendance  $attendance
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Attendance $attendance)
    {
        // Eager load the relationships for a more detailed response
        $attendance->load('classStudent.user', 'classStudent.class');
        return response()->json($attendance, 200);
    }

    /**
     * Update a single attendance record.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Attendance  $attendance
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Attendance $attendance)
    {
        // --- Validation ---
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // --- Database Operation ---
        $attendance->update([
            'status' => $request->status,
            'remarks' => $request->remarks,
        ]);
        
        return response()->json(['message' => 'Attendance record updated successfully.', 'data' => $attendance], 200);
    }

    /**
     * Remove a specific attendance record from the database.
     *
     * @param  \App\Models\Attendance  $attendance
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return response()->json(['message' => 'Attendance record deleted successfully.'], 200);
    }
}