<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentModel extends Model
{
    protected $table = 'parent_tbl';

    protected $fillable = [
        'idnumber',
        'firstname',
        'lastname',
        'email',
        'phone',
        'linked_id',
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'idnumber', 'idnumber');
    }

    public function student()
    {
        return $this->belongsTo(Students::class, 'linked_id', 'idnumber');
    }
}
