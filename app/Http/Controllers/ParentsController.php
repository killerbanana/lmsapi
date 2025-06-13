<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin;
use App\Models\Students;
use App\Models\Teachers;
use App\Models\ParentModel;
use Illuminate\Support\Facades\Hash;
use App\Services\RoleAbilitiesService;
use Illuminate\Support\Facades\Auth;
use App\Models\TeacherClass;
use App\Models\StudentClass;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class ParentsController extends Controller
{
    public function getLinkedStudents(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->usertype !== 'Parent') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $parent = ParentModel::where('idnumber', $user->idnumber)->first();

        if (!$parent) {
            return response()->json(['error' => 'Parent not found.'], 404);
        }
        
        $student = Students::where('idnumber', $parent->linked_id)
            ->with([
                'classes',       // Assuming pivot table exists
                'lessons'        // Pivot: lesson_student
            ])
            ->first();

        if (!$student) {
            return response()->json(['error' => 'No linked student found.'], 404);
        }

        return response()->json([
            'student' => $student
        ]);
    }

}
