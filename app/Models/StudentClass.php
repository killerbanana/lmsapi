<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentClass extends Model
{
     // The table associated with the model
    protected $table = 'class_students';  // Optional, only if the table name is different from the plural form of the model name

    // The attributes that are mass assignable
    protected $fillable = [
        'idnumber', 
        'class_id', 
    ];

    // Each entry belongs to one class
    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id', 'class_id');
    }

    /**
     * Relationship: Belongs to a student (User model by idnumber).
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }
}
