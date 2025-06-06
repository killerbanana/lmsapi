<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'name',
        'type',
        'url',
    ];

    /**
     * Relationship: Each resource belongs to a section.
     */
    public function section()
    {
        return $this->belongsTo(Sections::class);
    }
}
