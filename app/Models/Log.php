<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'level',
        'message',
        'context',
    ];

    /**
     * The attributes that should be cast.
     *
     * This will automatically convert the 'context' JSON from the database
     * into a PHP array and back when saving.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
    ];
}