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
    public function user(): BelongsTo
    {
        // This links the 'idnumber' on the 'class_students' table
        // to the 'idnumber' on the 'users' table.
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }

    /**
     * --- REMOVED: Deleted the duplicate 'student()' method ---
     * The 'user()' method already provides this relationship. Having both is redundant.
     */

    /**
     * Get all attendance records for this specific enrollment.
     * --- UPDATED: The HasMany type hint will now work because of the import ---
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'class_student_id');
    }
}