<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Models\Students;
use App\Models\Teachers;
use Illuminate\Support\Facades\Hash;
use App\Services\RoleAbilitiesService;
use App\Models\Classes;
use Illuminate\Support\Facades\Auth;


class ClassesController extends Controller
{
    public function createClass(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|unique:classes,class_id',
            'class_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);


        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $class = Classes::create([
            'class_id' => $request->class_id,
            'class_name' => $request->class_name,
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'Class added successfully',
            'data' => $class
        ], 201);
    }
}
