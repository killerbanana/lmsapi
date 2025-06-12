<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $table = 'lessons';

    protected $fillable = [
        'name',
        'idnumber',
        'class_id',
        'description',
    ];

    public $timestamps = true;

    /**
     * A lesson belongs to a class.
     */
    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id', 'class_id');
    }


    /**
     * A lesson has many sections.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'lesson_id');
    }

    /**
     * A lesson belongs to a user (instructor), by idnumber.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }

    /**
     * A lesson has many student progress records.
     */
    public function lessonStudents(): HasMany
    {
        return $this->hasMany(LessonStudent::class, 'lesson_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'lesson_student', 'lesson_id', 'idnumber', 'id', 'idnumber')
            ->withPivot('progress')
            ->withTimestamps();
    }
}
