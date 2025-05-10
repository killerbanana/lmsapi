<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClassesController;
use App\Http\Controllers\StudentClassController;
use App\Http\Controllers\TeacherClassController;
use App\Http\Controllers\LessonController;

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
    Route::post('/lesson/create', [LessonController::class, 'createLesson'])
        ->middleware('check.ability:create-lesson');
    
    Route::get('/lessons', [LessonController::class, 'getAllLessons'])
        ->middleware('check.ability:view-lessons');

    Route::post('/subject/assign/student', [StudentSubjectController::class, 'assignStudentToSubject'])
        ->middleware('check.ability:subject-assign-student');

    Route::post('/subject/assign/teacher', [StudentSubjectController::class, 'assignTeacherToSubject'])
        ->middleware('check.ability:subject-assign-teacher');

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
});
