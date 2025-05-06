<?php

namespace App\Services;

class RoleAbilitiesService
{
    // Define abilities for different user roles
    public static function getAbilities($role)
    {
        $roleAbilities = [
            'Administrator' => ['*'],
            'Teacher' => ['view-students', 'grade-students', 'update-profile', 'assign-student-subject', 'assign-student-class'],
            'Student' => ['view-grades', 'update-profile', 'answer-module'],
            'Parent' => ['read-only'],
        ];

        // Return the abilities for the given role, or default to 'read-only'
        return $roleAbilities[$role] ?? ['read-only']; 
    }
}
