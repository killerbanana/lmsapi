<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'posted_by',
        'audience',
        'published_at',
        'status',
    ];

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
