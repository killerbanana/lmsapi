<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAssessmentStudent extends Model
{
    protected $table = 'quiz_assessment_student';
    
    public function quizAssessment()
    {
        return $this->belongsTo(QuizAssessment::class);
    }

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_idnumber', 'idnumber');
    }
}


