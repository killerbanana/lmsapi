<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classes extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'class_id',
        'class_name',
        'description',
        'photo',
        'tag',
    ];

    public function students()
    {
        return $this->hasMany(Students::class, 'class_id', 'class_id');
    }
}
