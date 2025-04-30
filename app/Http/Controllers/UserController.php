<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\PersonalInfo;

class UserController extends Controller
{
    public function register(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:users,username',
            'idnumber' => 'required|string|unique:users,idnumber',
            'usertype' => 'required|in:admin,student,teacher',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
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
            'usertype' => $request->usertype,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        // Create or update the PersonalInfo for the user
        $personalInfo = PersonalInfo::updateOrCreate(
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

        // Return success response with user and personal info
        return response()->json([
            'message' => 'User and Personal Info created successfully!',
            'user' => $user,
            'personal_info' => $personalInfo
        ], 201);
    }

}
