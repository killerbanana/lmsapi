<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teachers extends Model
{
    // Specify the table name (optional if Laravel's pluralization matches)
    protected $table = 'teachers';

    // Specify the fields that are mass assignable
    protected $fillable = [
        'idnumber',
        'firstname',
        'lastname',
        'phone',
        'gender',
        'birthdate',
        'address',
        'photo',
        'email',
    ];

    // Enable timestamps (created_at, updated_at)
    public $timestamps = true;

    /**
     * Define the relationship to the User model.
     * Assumes the User model has 'idnumber' as primary or unique key.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }
}
