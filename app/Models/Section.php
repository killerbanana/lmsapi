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
     * The lesson this section belongs to.
     */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);  // check class name & namespace
    }

    /**
     * Resources like PDFs or videos linked to this section.
     */
    public function resources()
    {
        return $this->hasMany(SectionResource::class);
    }

    /**
     * Completion actions that are triggered when this section is completed.
     */
    public function completionActions()
    {
        return $this->hasMany(SectionCompletionAction::class);
    }
}

