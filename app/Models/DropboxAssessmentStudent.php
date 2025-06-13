<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DropboxAssessmentStudent extends Model
{
    protected $table = 'dropbox_assessment_student';

    protected $fillable = [
        'dropbox_assessment_id',
        'student_idnumber',
        'score',
        'submitted_at',
        'attempts',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function dropboxAssessment()
    {
        return $this->belongsTo(DropboxAssessment::class);
    }

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_idnumber', 'idnumber');
    }
}