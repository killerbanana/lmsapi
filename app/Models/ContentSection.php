<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentSection extends Model
{
    protected $fillable = ['section_id', 'introduction', 'content'];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
