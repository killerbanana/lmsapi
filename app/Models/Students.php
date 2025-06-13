<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Students extends Model
{
    // Explicitly define the table if it doesn't match the plural form
    protected $table = 'students';

    // Mass assignable attributes
    protected $fillable = [
        'idnumber', 
        'firstname', 
        'lastname', 
        'email', 
        'phone', 
        'gender', 
        'birthdate', 
        'address', 
        'fathername', 
        'fathercontact', 
        'mothername', 
        'mothercontact',
        'guardian_contact',
        'photo',
        'status',
    ];

    // Automatically maintain created_at and updated_at columns
    public $timestamps = true;

    /**
     * A student has one linked user account.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }

    /**
     * A student belongs to many classes via the class_students pivot table.
     */
    public function classes()
    {
        return $this->belongsToMany(
            Classes::class,
            'class_students',
            'idnumber',     // foreign key on pivot table for student
            'class_id',     // foreign key on pivot table for class
            'idnumber',     // local key on Students model
            'class_id'      // local key on Classes model
        );
    }

    /**
     * A student belongs to many lessons via the lesson_student pivot table.
     */
    public function lessons()
    {
        return $this->belongsToMany(Lesson::class, 'lesson_student', 'idnumber', 'lesson_id', 'idnumber', 'id')
                    ->withPivot('progress')
                    ->withTimestamps();
    }
}
