<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Students extends Model
{
    // The table associated with the model
    protected $table = 'students';  // Optional, only if the table name is different from the plural form of the model name

    // The attributes that are mass assignable
    protected $fillable = [
        'idnumber', 
        'section', 
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
        'mothercontact'
    ];

    // If your table has the default created_at and updated_at columns
    public $timestamps = true;

    /**
     * Define the relationship with the User model.
     * A PersonalInfo belongs to a User (in this case, by 'idnumber').
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }

    public function classes()
    {
        return $this->belongsToMany(Classes::class, 'class_student');
    }
}
