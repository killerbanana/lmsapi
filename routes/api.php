<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\StudentSubjectController;
use App\Http\Controllers\ClassesController;
use App\Http\Controllers\StudentClassController;

// Default fallback route
Route::get('/', function () {
    return response()->json(['message' => 'Not Found'], 404);
});

// Public Auth Route
Route::post('/login', [UserController::class, 'login']);

// Protected Routes (Sanctum Auth)
Route::middleware('auth:sanctum')->group(function () {

    // Registration Routes
    Route::post('/register/student', [UserController::class, 'registerStudent'])
        ->middleware('check.ability:register-student');

    Route::post('/register/teacher', [UserController::class, 'registerTeacher'])
        ->middleware('check.ability:register-teacher');

    // Subject Routes
    Route::post('/subject/create', [SubjectController::class, 'createSubject'])
        ->middleware('check.ability:create-subject');

    Route::post('/studentsubject/assign/student', [StudentSubjectController::class, 'assignStudentToSubject'])
        ->middleware('check.ability:assign-student-subject');

    Route::post('/studentsubject/assign/teacher', [StudentSubjectController::class, 'assignTeacherToSubject'])
        ->middleware('check.ability:assign-teacher-subject');

    // Class Routes
    Route::post('/class/create', [ClassesController::class, 'createClass'])
        ->middleware('check.ability:create-class');

    Route::post('/studentclass/create', [StudentClassController::class, 'assignStudentToClass'])
        ->middleware('check.ability:assign-class-student');
});
