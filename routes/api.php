<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\StudentSubjectController;



Route::post('/login', [UserController::class, 'login']);

Route::get('/', function () {
    return response()->json(['message' => ''], 404);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/register/student', [UserController::class, 'registerStudent'])->middleware('check.ability:register-student');
    Route::post('/register/teacher', [UserController::class, 'registerTeacher'])->middleware('check.ability:register-teacher');
});

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/subject/create', [SubjectController::class, 'createSubject'])
    ->middleware('check.ability:create-subject');
    
    Route::post('/studentsubject/assign/student', [StudentSubjectController::class, 'assignStudentToSubject'])
        ->middleware('check.ability:assign-student-subject');

    Route::post('/studentsubject/assign/teacher', [StudentSubjectController::class, 'assignTeacherToSubject'])
        ->middleware('check.ability:assign-teacher-subject');
});
