<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonStudent extends Model
{
    protected $table = 'lesson_student';

    protected $fillable = [
        'lesson_id',
        'idnumber',
        'progress', // optional field if you're tracking it
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }
}
