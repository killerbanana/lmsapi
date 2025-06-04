<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClassesController;
use App\Http\Controllers\StudentClassController;
use App\Http\Controllers\TeacherClassController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\StudentSubjectController;
use App\Http\Controllers\OtpController;
use Illuminate\Support\Facades\Auth;


// Default fallback route
Route::get('/', function () {
    return response()->json(['message' => 'Not Found'], 404);
});

Route::post('/send-otp', [OtpController::class, 'sendOtp']);
Route::post('/verify-otp', [OtpController::class, 'verifyOtp']);

// Public Auth Route
Route::post('/login', [UserController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    //Uer
    Route::get('/currentuser', [UserController::class, 'getCurrentUser']);

    Route::get('/check-auth', [UserController::class, 'checkAuthStatus']);

    Route::put('/user/{idnumber}/change-password', [UserController::class, 'changePassword'])
    ->middleware('auth:sanctum'); 
    
    // Registration Routes
    Route::post('/register/student', [UserController::class, 'registerStudent'])
        ->middleware('check.ability:register-student');

    Route::post('/register/teacher', [UserController::class, 'registerTeacher'])
        ->middleware('check.ability:register-teacher');

    // Subject Routes
    Route::post('/lesson/create', [LessonController::class, 'createLesson'])
        ->middleware('check.ability:create-lesson');
    
    Route::get('/lessons', [LessonController::class, 'getAllLessons'])
        ->middleware('check.ability:view-lessons');

    // Route::post('/subject/assign/student', [StudentSubjectController::class, 'assignStudentToSubject'])
    //     ->middleware('check.ability:subject-assign-student');

    // Route::post('/subject/assign/teacher', [StudentSubjectController::class, 'assignTeacherToSubject'])
    //     ->middleware('check.ability:subject-assign-teacher');

    // Class Routes
    Route::post('/class/create', [ClassesController::class, 'createClass'])
        ->middleware('check.ability:create-class');

    Route::get('/classes', [TeacherClassController::class, 'getAllClass'])
        ->middleware('check.ability:view-classes');

    Route::post('/class/assign/student', [StudentClassController::class, 'assignStudentToClass'])
        ->middleware('check.ability:class-assign-student');

    Route::post('/class/assign/teacher', [TeacherClassController::class, 'assignTeacherToClass'])
        ->middleware('check.ability:class-assign-teacher');

    Route::post('/class/assign/subject', [TeacherClassController::class, 'assignTeacherToClass'])
        ->middleware('check.ability:class-assign-teacher');
        
    //Teachers
    Route::get('/teachers', [UserController::class, 'getTeachers'])
    ->middleware('check.ability:view-teachers');
    
    Route::put('/teacher/{idnumber}', [UserController::class, 'updateTeacherInfo'])
    ->middleware('check.ability:update-teacher');

    Route::delete('/teacher/{idnumber}', [UserController::class, 'deleteTeacher'])
        ->middleware('check.ability:delete-teacher');

    //Students
    Route::get('/students', [UserController::class, 'getStudents'])
    ->middleware('check.ability:view-students');

    Route::put('/student/{idnumber}', [UserController::class, 'updateStudentInfo'])
    ->middleware('check.ability:update-student');

    Route::delete('/student/{idnumber}', [UserController::class, 'deleteStudent'])
        ->middleware('check.ability:delete-student');

    //Parents
    Route::get('/parents', [UserController::class, 'getparents'])
    ->middleware('check.ability:view-parents');

    Route::put('/parent/{idnumber}', [UserController::class, 'updateParentInfo'])
    ->middleware('check.ability:update-parent');

    Route::delete('/parent/{idnumber}', [UserController::class, 'deleteParent'])
        ->middleware('check.ability:delete-parent');

    //EMAIL
});
