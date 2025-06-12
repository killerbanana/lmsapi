<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClassesController;
use App\Http\Controllers\StudentClassController;
use App\Http\Controllers\TeacherClassController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\StudentSubjectController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\TeachersController;
use App\Http\Controllers\LessonStudentController;
use App\Http\Controllers\AnnouncementController;
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

    // Lessons Routes
    Route::post('/lesson/create', [LessonController::class, 'createLesson'])
        ->middleware('check.ability:create-lesson');
    
    Route::get('/lessons', [LessonController::class, 'getAllLessons'])
        ->middleware('check.ability:view-lessons');

    Route::put('/lessons/{id}', [LessonController::class, 'updateLesson'])->middleware('check.ability:update-lessons');

    Route::delete('/lessons/{id}', [LessonController::class, 'deleteLesson'])->middleware('check.ability:delete-lessons');

    Route::post('/lessons/add-student', [LessonController::class, 'assignStudentToLessons'])->middleware('check.ability:add-student-lessons');

     Route::post('/lesson/update-progress', [LessonStudentController::class, 'updateLessonProgress'])->middleware('check.ability:update-lesson-progress');

    // Class Routes
    Route::post('/class/create', [ClassesController::class, 'createClass'])
        ->middleware('check.ability:create-class');

    Route::get('/classes', [TeacherClassController::class, 'getAllClass'])
        ->middleware('check.ability:view-classes');

    Route::get('/linked-classes', [ClassesController::class, 'getAllClass'])
        ->middleware('check.ability:view-classes-all');


    Route::post('/class/assign/student', [StudentClassController::class, 'assignStudentToClass'])
        ->middleware('check.ability:class-assign-student');

    Route::post('/class/assign/teacher', [TeacherClassController::class, 'assignTeacherToClass'])
        ->middleware('check.ability:class-assign-teacher');

    Route::post('/class/assign/subject', [TeacherClassController::class, 'assignTeacherToClass'])
        ->middleware('check.ability:class-assign-teacher');

    Route::post('/classes/{id}', [ClassesController::class, 'updateClass'])->middleware('check.ability:update-clas');
    Route::delete('/classes/{id}', [ClassesController::class, 'deleteClass'])->middleware('check.ability:delete-class');
        
    //Teachers
    Route::get('/teachers', [UserController::class, 'getTeachers'])
    ->middleware('check.ability:view-teachers');
    
    Route::post('/teacher/{idnumber}', [UserController::class, 'updateTeacherInfo'])
    ->middleware('check.ability:update-profile');

    Route::delete('/teacher/{idnumber}', [UserController::class, 'deleteTeacher'])
        ->middleware('check.ability:delete-teacher');

    //Students
    Route::get('/students', [UserController::class, 'getStudents'])
    ->middleware('check.ability:view-students');

    Route::post('/student/{idnumber}', [UserController::class, 'updateStudentInfo'])
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


    //SECTION
    Route::post('/section', [SectionController::class, 'create'])
        ->middleware('check.ability:create-section');

    Route::get('/section', [SectionController::class, 'getAllSection'])->middleware('check.ability:view-section');

    Route::put('/section/{id}', [SectionController::class, 'update'])->middleware('check.ability:update-section');

    Route::post('/section-progress/update', [SectionController::class, 'updateSectionProgress'])->middleware('check.ability:update-section-progress');

    Route::post('/section/assessment', [SectionController::class, 'createAssessment'])
        ->middleware('check.ability:create-section-assessme nt');



    Route::get('/announcements', [AnnouncementController::class, 'index'])->middleware('check.ability:post-announcement');

    Route::post('/announcements', [AnnouncementController::class, 'store'])->middleware('check.ability:post-announcement');

    Route::get('/announcements/{id}', [AnnouncementController::class, 'show'])->middleware('check.ability:get-announcement');

    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy'])->middleware('check.ability:delete-announcement');

    Route::put('/announcements/{id}', [AnnouncementController::class, 'update'])->middleware('check.ability:update-announcement');

    Route::post('/send-sms', [AnnouncementController::class, 'sendSms'])->middleware('check.ability:send-sms');
});
