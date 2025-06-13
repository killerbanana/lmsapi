<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'title',
        'introduction',
        'require_for_completion',
        'completion_time_estimate',
        'is_complete_on_visit',
        'type',
        'subtype',
    ];

    /**
     * Get the lesson this section belongs to.
     */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the resources (e.g., PDFs, videos) linked to this section.
     */
    public function resources()
    {
        return $this->hasMany(SectionResource::class);
    }

    /**
     * Get the dropbox assessments for this section.
     */
    public function dropboxAssessments()
    {
        return $this->hasMany(DropboxAssessment::class);
    }

    /**
     * Get the quiz assessments for this section.
     */
    public function quizAssessments()
    {
        return $this->hasMany(QuizAssessment::class);
    }

    /**
     * Get the completion actions for this section.
     */
    public function completionActions()
    {
        return $this->hasMany(SectionCompletionAction::class);
    }

    /**
     * Get the content sections related to this section.
     */
    public function contentSections()
    {
        return $this->hasMany(ContentSection::class);
    }
}
