<?php

namespace App\Services;

class RoleAbilitiesService
{
    // Define abilities for different user roles
    public static function getAbilities($role)
    {
        $roleAbilities = [
            'Administrator' => ['*'],
            'Teacher' => ['score-quiz-submission', 'check-quiz-submissions', 'section-student-linked','send-sms', 'get-announcement',  'post-announcement', 'delete-announcement', 'view-students', 'grade-students', 'update-profile', 'subject-assign-student', 'class-assign-student', 'create-lesson',
        'view-lessons', 'update-announcement', 'create-section-assessment', 'view-classes', 'update-lessons', 'delete-lessons','create-section', 'view-section', 'update-section', 'view-classes-all', 'add-student-lessons', 'update-lesson-progress', 'update-section-progress'],
            'Student' => ['section-student-linke', 'submit-quiz-answer', 'section-student', 'get-announcement',  'view-grades', 'update-profile', 'answer-module', 'view-classes', 'view-lessons', 'view-section', 'update-section', 'view-classes-all', 'update-lesson-progress', 'update-section-progress'],
            'Parent' => ['read-only', 'view-students-parent'],
        ];

        // Return the abilities for the given role, or default to 'read-only'
        return $roleAbilities[$role] ?? ['read-only']; 
    }
}
