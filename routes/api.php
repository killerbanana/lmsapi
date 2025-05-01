<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\StudentSubjectController;


Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::middleware('auth:sanctum')->get('/readstudent', [UserController::class, 'read']);
// routes/api.php
Route::middleware('auth:sanctum')->post('/subject/create', [SubjectController::class, 'createSubject']);
Route::middleware('auth:sanctum')->post('/studentsubject/create', [StudentSubjectController::class, 'createStudentSubject']);

