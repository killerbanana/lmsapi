<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'idnumber',
        'section_id',
        'title',
        'instructions',
        'points',
        'max_score',
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
        return $this->belongsToMany(
            Students::class,                 // The target model is Student
            'quiz_assessment_student',      // The name of the pivot table
            'quiz_assessment_id',           // Foreign key on pivot table for this model
            'student_idnumber',             // Foreign key on pivot table for the Student model
            'id',                           // Parent key on this model (QuizAssessment)
            'idnumber'                      // The key on the Student model to join with
        )->withPivot('score', 'submitted_at', 'attempts', 'answer_text', 'file_path', 'feedback');
    }
    
}
