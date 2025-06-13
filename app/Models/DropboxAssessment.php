<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DropboxAssessment extends Model
{
     protected $fillable = [
        'section_id',
        'title',
        'max_score',
        'points',
        'category',
        'start_date',
        'due_date',
        'lesson',
        'grading_scale',
        'max_attempts',
        'allow_late',
        'grading',
        'instructions',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function studentLinks()
    {
        return $this->hasMany(DropboxAssessmentStudent::class);
    }

    public function students()
    {
        return $this->belongsToMany(Students::class, 'dropbox_assessment_student', 'dropbox_assessment_id', 'student_idnumber', 'id', 'idnumber')
                    ->withPivot(['score', 'submitted_at', 'attempts'])
                    ->withTimestamps();
    }
}
