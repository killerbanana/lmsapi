<?php

namespace App\Services;

class RoleAbilitiesService
{
    // Define abilities for different user roles
    public static function getAbilities($role)
    {
        $roleAbilities = [
            'Administrator' => ['*'],
            'Teacher' => ['view-students', 'grade-students', 'update-profile', 'subject-assign-student', 'class-assign-student', 'create-lesson',
        'view-lessons', 'view-classes', 'update-lessons', 'delete-lessons','create-section', 'view-section', 'update-section', 'view-classes-all'],
            'Student' => ['view-grades', 'update-profile', 'answer-module', 'view-classes', 'view-lessons', 'view-section', 'update-section', 'view-classes-all'],
            'Parent' => ['read-only'],
        ];

        // Return the abilities for the given role, or default to 'read-only'
        return $roleAbilities[$role] ?? ['read-only']; 
    }
}
