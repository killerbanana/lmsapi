<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $table = 'subjects'; // Explicitly define the table name if not plural 'subjects'

    // Define the fillable fields for mass assignment
    protected $fillable = ['subject_id', 'name'];

    // If your table has the default created_at and updated_at columns
    public $timestamps = true;

    public function users()
    {
        return $this->belongsToMany(User::class, 'studentsubject', 'subject_id', 'idnumber')
                    ->withPivot('usertype')
                    ->withTimestamps();
    }
}
