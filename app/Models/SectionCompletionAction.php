<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionCompletionAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'action_type',
        'parameters',
    ];

    protected $casts = [
        'parameters' => 'array', // Automatically decode/encode JSON
    ];

    /**
     * Relationship: Each completion action belongs to a section.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
