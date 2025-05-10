<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentLesson extends Model
{
    // Table name is 'studentsubjects', not 'studentsubject'
    protected $table = 'lesson_students';

    // The fillable attributes to allow mass assignment
    protected $fillable = ['idnumber', 'lesson_id', 'usertype']; 

    // Define the relationship with the User model
    public function user()
    {
        // Foreign key: idnumber refers to users.idnumber
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }

    // Define the relationship with the Subject model
    public function lesson()
    {
        // Foreign key: subject_id refers to subjects.subject_id
        return $this->belongsTo(Subject::class, 'lesson_id', 'lesson_id');
    }
}
