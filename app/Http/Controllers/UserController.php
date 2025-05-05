<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Students;
use App\Models\Teachers;
use Illuminate\Support\Facades\Hash;
use App\Services\RoleAbilitiesService;

class UserController extends Controller
{

    public function read(Request $request)
    {
        // Check if the user is authenticated
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Admin with [*] or user with specific permission can access
        if (! $request->user()->tokenCan('view-students') && $request->user()->usertype !== 'Administrator') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    
        // Get all students (you can filter by usertype or any logic)
        $students = PersonalInfo::whereHas('user', function ($query) {
            $query->where('usertype', 'Student');
        })->get();;
    
        return response()->json([
            'message' => 'Students retrieved successfully',
            'data' => $students
        ]);
    }

    public function registerStudent(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:users,username',
            'idnumber' => 'required|string|unique:users,idnumber',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'section' => 'nullable|string',
            'firstname' => 'nullable|string',
            'lastname' => 'nullable|string',
            'phone' => 'nullable|string',
            'gender' => 'nullable|in:male,female,other',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string',
            'fathername' => 'nullable|string',
            'fathercontact' => 'nullable|string',
            'mothername' => 'nullable|string',
            'mothercontact' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Create the user
        $user = User::create([
            'username' => $request->username,
            'idnumber' => $request->idnumber,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'usertype' => 'Student',
        ]);

        
        $personalInfo = Students::updateOrCreate(
            ['idnumber' => $user->idnumber],  // Check if the idnumber exists
            [
                'section' => $request->section,
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'phone' => $request->phone,
                'gender' => $request->gender,
                'birthdate' => $request->birthdate,
                'address' => $request->address,
                'fathername' => $request->fathername,
                'fathercontact' => $request->fathercontact,
                'mothername' => $request->mothername,
                'mothercontact' => $request->mothercontact,
            ]
        );

        return response()->json([
            'message' => 'Student account created successfully!',
            'idnumber' => $personalInfo->idnumber
        ], 201);
    }


    public function registerTeacher(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:users,username',
            'idnumber' => 'required|string|unique:users,idnumber',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'firstname' => 'nullable|string',
            'lastname' => 'nullable|string',
            'phone' => 'nullable|string',
            'gender' => 'nullable|in:male,female,other',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'username' => $request->username,
            'idnumber' => $request->idnumber,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'usertype' => 'Teacher',
        ]);

        $personalInfo = Teachers::updateOrCreate(
            ['idnumber' => $user->idnumber],
            [
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'phone' => $request->phone,
                'gender' => $request->gender,
                'birthdate' => $request->birthdate,
                'address' => $request->address,
            ]
        );

        return response()->json([
            'message' => 'Teacher account created successfully!',
            'idnumber' => $personalInfo->idnumber
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string', // or 'email' if you're using email
            'password' => 'required|string',
        ]);

        // Try to find the user by username
        $user = User::where('username', $credentials['username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        
        $abilities = RoleAbilitiesService::getAbilities($user->usertype);

        // Delete previous token
        $user->tokens->each(function ($token) {
            $token->delete();  // Delete all previous tokens
        });

        // Create a token using Sanctum
        $token = $user->createToken('auth_token', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }


    public function getStudents()
    {
        // Check if authenticated user has 'view-students' ability
        if (!Auth::user()->tokenCan('view-students')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Fetch users with usertype = Student
        $students = User::where('usertype', 'Student')->get();

        return response()->json($students);
    }

}
