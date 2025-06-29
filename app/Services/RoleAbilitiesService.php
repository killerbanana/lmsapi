<?php

namespace App\Services;

class RoleAbilitiesService
{
    // Define abilities for different user roles
    public static function getAbilities($role)
    {
        $roleAbilities = [
            'Administrator' => ['*'],
            'Teacher' => [
                'delete-section',
                'grade-dropbox-submission',
                'view-dropbox-submissions',
                'section-student-linked',
                'view-parents',
                'delete-attendance',
                'update-attendance',
                'view-attendance',
                'create-attendance',
                'update-section-progress',
                'grade-student',
                'changePassword',
                'score-quiz-submission',
                'check-quiz-submissions',
                'section-student-linked',
                'send-sms',
                'get-announcement',
                'post-announcement',
                'delete-announcement',
                'view-students',
                'grade-students',
                'update-profile',
                'subject-assign-student',
                'class-assign-student',
                'create-lesson',
                'view-lessons',
                'update-announcement',
                'create-section-assessment',
                'grade-dropbox-submission',
                'view-classes',
                'update-lessons',
                'delete-lessons',
                'create-section',
                'view-section',
                'update-section',
                'view-classes-all',
                'add-student-lessons',
                'update-lesson-progress',
                'update-section-progress'
            ],
            'Student' => ['submit-dropbox-assessment', 'view-teachers', 'view-parents', 'view-dropbox-submissions', 'view-own-attendance', 'update-section-progress', 'changePassword', 'section-student', 'section-student-linked', 'submit-quiz-answer', 'section-student', 'get-announcement',  'view-grades', 'update-profile', 'answer-module', 'view-classes', 'view-lessons', 'view-section', 'update-section', 'view-classes-all', 'update-lesson-progress', 'update-section-progress'],
            'Parent' => ['view-teachers', 'view-own-attendance', 'section-student-linked', 'view-parents', 'changePassword', 'read-only', 'view-students-parent', 'get-announcement'],
        ];

        // Return the abilities for the given role, or default to 'read-only'
        return $roleAbilities[$role] ?? ['read-only'];
    }
}
