<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\StudentClass;
use App\Models\Classes; // Assuming you have a Classes model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
        // --- Validation ---
        // Validate that class_id and a valid date are provided.
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|string|exists:classes,class_id',
            'date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $classId = $request->input('class_id');
        $date = $request->input('date');

        // --- Data Retrieval ---
        // Get all students enrolled in the specified class.
        // Eager load the user details and their attendance ONLY for the specified date.
        $students = StudentClass::where('class_id', $classId)
            ->with(['user:id,idnumber,username', 'attendances' => function ($query) use ($date) {
                $query->where('attendance_date', $date);
            }])
            ->get();

        // --- Response Formatting ---
        // Format the data for a clean API response.
        $attendanceData = $students->map(function ($enrollment) {
            $attendanceRecord = $enrollment->attendances->first();
            return [
                'class_student_id' => $enrollment->id,
                'idnumber' => $enrollment->user->idnumber,
                'username' => $enrollment->user->username,
                'status' => $attendanceRecord->status ?? null, // Status if attendance was taken, otherwise null
                'remarks' => $attendanceRecord->remarks ?? null,
            ];
        });

        return response()->json([
            'class_id' => $classId,
            'attendance_date' => $date,
            'attendance_sheet' => $attendanceData
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