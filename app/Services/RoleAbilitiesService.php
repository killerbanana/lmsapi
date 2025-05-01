<?php

namespace App\Services;

class RoleAbilitiesService
{
    // Define abilities for different user roles
    public static function getAbilities($role)
    {
        $roleAbilities = [
            'Administrator' => ['*'],
            'Teacher' => ['view-students', 'grade-students', 'update-profile'],
            'Student' => ['view-grades', 'update-profile', 'answer-module'],
            'Parent' => ['read-only']
        ];

        // Return the abilities for the given role, or default to 'read-profile'
        return $roleAbilities[$role] ?? ['read-only']; 
    }
}
