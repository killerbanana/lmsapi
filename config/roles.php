<?php

return [
    'Administrator' => ['*'],
    'Teacher' => ['view-students', 'grade-students', 'update-profile'],
    'Student' => ['view-grades', 'update-profile', 'answer-module'],
    'Parent' => ['read-only'],
];