<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lessons extends Model
{
    protected $table = 'lessons'; // Explicitly define the table name if not plural 'subjects'

    // Define the fillable fields for mass assignment
    protected $fillable = ['name', 'class_id', 'idnumber'];

    // If your table has the default created_at and updated_at columns
    public $timestamps = true;

    public function studentLesson()
    {
        return $this->belongsToMany(StudentLesson::class, 'lesson_students', 'idnumber')
                    ->withTimestamps();
    }

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id', 'class_id');
    }
}
