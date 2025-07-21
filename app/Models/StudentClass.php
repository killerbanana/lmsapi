<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// --- ADDED: Import statements for relationships ---
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentClass extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'class_students';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'idnumber',
        'class_id',
        'status', // Added status back as it was in the original schema
        'grade',
    ];

    /**
     * Get the class that this enrollment belongs to.
     * --- UPDATED: Added BelongsTo return type hint ---
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id', 'class_id');
    }

    /**
     * Get the student (user) that this enrollment belongs to.
     * --- UPDATED: Kept this as the primary relationship to the user ---
     */
    public function user()
    {
        // This links 'class_students.idnumber' to 'users.idnumber'
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }

    /**
     * Get the attendance records for the class enrollment.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'class_student_id', 'id');
    }

    public function student()
    {
        // CORRECTED: This now points to the Student model.
        return $this->belongsTo(Students::class, 'idnumber', 'idnumber');
    }
}