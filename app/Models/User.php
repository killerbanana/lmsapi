<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',    // renamed from 'name' to 'username'
        'idnumber',    // added idnumber
        'usertype',    // added usertype
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationship to PersonalInfo (if user has personal details)
    public function personalInfo()
    {
        return $this->hasOne(PersonalInfo::class, 'idnumber', 'idnumber');
    }

    public function studentSubjects()
    {
        return $this->hasMany(StudentSubject::class, 'idnumber');
    }

    public function studentClass()
    {
        return $this->hasMany(StudentClass::class, 'idnumber');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'studentsubject', 'idnumber', 'subject_id')
                    ->withPivot('usertype') 
                    ->withTimestamps(); 
    }
}
