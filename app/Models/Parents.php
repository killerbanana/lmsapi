<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parents extends Model
{
    // The table associated with the model
    protected $table = 'parent_tbl';  // Optional, only if the table name is different from the plural form of the model name

    // The attributes that are mass assignable
    protected $fillable = [
        'idnumber', 
        'firstname', 
        'lastname', 
        'email', 
        'phone', 
        'linked_id'
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
}
