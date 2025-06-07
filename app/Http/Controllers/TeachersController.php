<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Students;
use App\Models\Teachers;
use App\Models\Parents;

class TeachersController extends Controller
{
    public function getStudents(Request $request)
    {
        $perPage = $request->query('perPage', 10);  // default 10 per page

        $paginated = Students::paginate($perPage);

        return response()->json([
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'students' => $paginated->items(),
        ], 200);
    }

    public function getTeachers(Request $request)
    {
        $perPage = $request->query('perPage', 10);  // default 10 per page

        $paginated = Teachers::paginate($perPage);

        return response()->json([
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'teachers' => $paginated->items(),
        ], 200);
    }

    public function getParents(Request $request)
    {
        $perPage = $request->query('perPage', 10);  // default 10 per page

        $paginated = Parents::paginate($perPage);

        return response()->json([
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'parents' => $paginated->items(),
        ], 200);
    }
}
