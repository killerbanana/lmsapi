<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classes extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'class_id',
        'class_name',
        'description',
        'photo',
        'tag',
        'status',
    ];

    /**
     * Relationship: class has many entries in the pivot table (class_students)
     */
    public function studentClasses()
    {
        return $this->hasMany(StudentClass::class, 'class_id', 'class_id');
    }

    /**
     * Relationship: class has many students through the pivot table
     */
    public function students()
    {
        return $this->belongsToMany(Students::class, 'class_students', 'class_id', 'idnumber', 'class_id', 'idnumber');
    }
}
