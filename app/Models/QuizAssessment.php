<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'idnumber',
        'section_id', // ✅ changed from 'lesson'
        'title',
        'instructions',
        'points',
        'category',
        'start',
        'due',
        'grading_scale',
        'grading',
        'max_attempts',
        'allow_late',
        'timed',
        'instant_feedback',
        'release_grades',
        'grading_method',
        'disable_past_due',
        'autocomplete_on_retake',
        'randomize_order',
        'allow_review',
        'allow_jump',
        'show_in_results',
        'library',
    ];

    protected $casts = [
        'start' => 'datetime',
        'due' => 'datetime',
        'allow_late' => 'boolean',
        'timed' => 'boolean',
        'instant_feedback' => 'boolean',
        'disable_past_due' => 'boolean',
        'autocomplete_on_retake' => 'boolean',
        'randomize_order' => 'boolean',
        'allow_review' => 'boolean',
        'allow_jump' => 'boolean',
        'show_in_results' => 'array',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }   

    public function students()
    {
        return $this->belongsToMany(Students::class, 'quiz_assessment_student', 'quiz_assessment_id', 'student_idnumber', 'id', 'idnumber');
    }
    
}
