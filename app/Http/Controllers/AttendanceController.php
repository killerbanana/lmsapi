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
        // This part remains the same, as it's correct.
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|string|exists:classes,class_id',
            'date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $classId = $request->input('class_id');
        $date = $request->input('date');

        // --- 2. Data Retrieval ---
        // Refactored to use the DB query builder with joins instead of Eloquent relationships.
        // This approach is more direct and avoids potential issues with model relationship definitions.
        $studentsWithAttendance = DB::table('class_students')
            ->join('users', 'class_students.idnumber', '=', 'users.idnumber')
            ->join('students', 'users.idnumber', '=', 'students.idnumber')
            ->leftJoin('attendances', function ($join) use ($date) {
                // The leftJoin ensures all students are returned, even if they don't have an attendance record for the date.
                $join->on('class_students.id', '=', 'attendances.class_student_id')
                     ->where('attendances.attendance_date', '=', $date);
            })
            ->where('class_students.class_id', $classId)
            ->select(
                'class_students.id as class_student_id',
                'users.idnumber',
                // 'users.username', // This was commented out in your original mapping, so I've kept it out of the final select.
                'students.firstname',
                'students.lastname',
                'attendances.status',
                'attendances.remarks'
            )
            ->get();

        // --- 3. Response Formatting ---
        // The mapping is now simpler because the data is already in a flat structure from the query.
        $attendanceData = $studentsWithAttendance->map(function ($student) {
            return [
                'class_student_id' => $student->class_student_id,
                'idnumber' => $student->idnumber,
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'status' => $student->status, // This will be null if no attendance record was found by the leftJoin.
                'remarks' => $student->remarks, // This will also be null if no record was found.
            ];
        });

        // --- 4. Return JSON Response ---
        return response()->json([
            'class_id' => $classId,
            'attendance_date' => $date,
            'attendance_sheet' => $attendanceData, // No need for ->values() here as ->map() on a collection preserves keys, but the final JSON will be an array anyway.
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
        $user = Auth::user();

        // 1. Check if the authenticated user is a student
        if ($user->usertype !== 'Student') {
            return response()->json(['message' => 'This action is only for students.'], 403);
        }

        // 2. Get all attendance records for that student's enrollments.
        // This is a more direct way to query through relationships.
        $attendances = Attendance::whereHas('classStudent', function ($query) use ($user) {
                $query->where('idnumber', $user->idnumber);
            })
            ->with('classStudent.class:class_id,class_name') // Eager load class details
            ->get();

        // 3. Format the response
        $formattedAttendances = $attendances->map(function($record) {
            return [
                'class_id' => $record->classStudent->class->class_id,
                'class_name' => $record->classStudent->class->class_name,
                'attendance_date' => $record->attendance_date->format('Y-m-d'),
                'status' => $record->status,
                'remarks' => $record->remarks
            ];
        });

        return response()->json($formattedAttendances, 200);
    }

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