<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSubject extends Model
{
    // Table name is 'studentsubjects', not 'studentsubject'
    protected $table = 'studentsubjects';

    // The fillable attributes to allow mass assignment
    protected $fillable = ['idnumber', 'subject_id', 'user_type'];

    // Define the relationship with the User model
    public function user()
    {
        // Foreign key: idnumber refers to users.idnumber
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }

    // Define the relationship with the Subject model
    public function subject()
    {
        // Foreign key: subject_id refers to subjects.subject_id
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }
}
