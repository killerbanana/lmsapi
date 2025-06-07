<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin;
use App\Models\Students;
use App\Models\Teachers;
use App\Models\Parents;
use Illuminate\Support\Facades\Hash;
use App\Services\RoleAbilitiesService;
use Illuminate\Support\Facades\Auth;
use App\Models\TeacherClass;
use Illuminate\Support\Facades\Cache;

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
        $url = null;
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
            'photo' => 'nullable|file|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');

            $factory  = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
            $firebase = $factory->create();
            $bucket = $firebase->createStorage()->getBucket();

            $firebaseFilePath = 'users/photo_' . uniqid() . '_' . $file->getClientOriginalName();

            $bucket->upload(
                fopen($file->getRealPath(), 'r'),
                ['name' => $firebaseFilePath]
            );

            $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";
        }

        // Start DB transaction
        \DB::beginTransaction();

        try {
            // Create Student User
            $studentUser = User::create([
                'username' => $request->username,
                'idnumber' => $request->idnumber,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'usertype' => 'Student',
            ]);

            // Store student personal info
            $personalInfo = Students::updateOrCreate(
                ['idnumber' => $studentUser->idnumber],
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
                    'photo' => $url,
                ]
            );

            // Register father user and parent record
            if ($request->filled('fathername') || $request->filled('fathercontact')) {
                $fatherId = $studentUser->idnumber . '-father';

                User::create([
                    'username' => $fatherId,
                    'idnumber' => $fatherId,
                    'email' => $fatherId . '@example.com', // Placeholder email
                    'password' => bcrypt('parent123'), // Default password
                    'usertype' => 'Parent',
                ]);

                Parents::create([
                    'idnumber' => $fatherId,
                    'firstname' => $request->fathername,
                    'lastname' => $request->lastname,
                    'email' => $fatherId . '@example.com',
                    'phone' => $request->fathercontact,
                    'linked_id' => $studentUser->idnumber,
                    'photo' => $url,
                ]);
            }

            // Register mother user and parent record
            if ($request->filled('mothername') || $request->filled('mothercontact')) {
                $motherId = $studentUser->idnumber . '-mother';

                User::create([
                    'username' => $motherId,
                    'idnumber' => $motherId,
                    'email' => $motherId . '@example.com',
                    'password' => bcrypt('parent123'),
                    'usertype' => 'Parent',
                ]);

                Parents::create([
                    'idnumber' => $motherId,
                    'firstname' => $request->mothername,
                    'lastname' => $request->lastname,
                    'email' => $motherId . '@example.com',
                    'phone' => $request->mothercontact,
                    'linked_id' => $studentUser->idnumber,
                    'photo' => $url,
                ]);
            }

            \DB::commit();

            return response()->json([
                'message' => 'Student and parent accounts created successfully!',
                'idnumber' => $personalInfo->idnumber,
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Registration failed', 'details' => $e->getMessage()], 500);
        }
    }
    public function deleteParent($idnumber)
    {
        try {
            $deleted = User::where('idnumber', $idnumber)->delete();

            if ($deleted) {
                return response()->json(['message' => 'Parent deleted successfully.']);
            } else {
                return response()->json(['error' => 'Parent not found.'], 404);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => 'Deletion failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function updateParentInfo(Request $request, $idnumber)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'nullable|string',
            'lastname' => 'nullable|string',
            'phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $parent = Parents::where('idnumber', $idnumber)->first();

        if (!$parent) {
            return response()->json(['message' => 'Parent not found.'], 404);
        }

        $parent->update($request->only(['firstname', 'lastname', 'phone']));

        return response()->json(['message' => 'Parent info updated successfully.']);
    }

    public function updateStudentInfo(Request $request, $idnumber)
    {
        $validator = Validator::make($request->all(), [
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
        

        $student = Students::where('idnumber', $idnumber)->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $student->update($request->only([
            'section',
            'firstname',
            'lastname',
            'phone',
            'gender',
            'birthdate',
            'address',
            'fathername',
            'fathercontact',
            'mothername',
            'mothercontact',
        ]));

        return response()->json(['message' => 'Student personal info updated successfully.']);
    }



    public function deleteStudent($idnumber)
    {
        \DB::beginTransaction();

        try {
            // Delete Parent users (will auto-delete from parents table due to cascade)
            foreach (['father', 'mother'] as $relation) {
                $parentId = $idnumber . '-' . $relation;
                User::where('idnumber', $parentId)->delete();
            }

            // Delete Student user (cascades to students table)
            User::where('idnumber', $idnumber)->delete();

            \DB::commit();
            return response()->json(['message' => 'Student and parents deleted successfully.']);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Deletion failed', 'details' => $e->getMessage()], 500);
        }
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
            'photo' => 'nullable|file|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $url = null;

        $user = User::create([
            'username' => $request->username,
            'idnumber' => $request->idnumber,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'usertype' => 'Teacher',
        ]);

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');

            $factory  = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
            $firebase = $factory->create();
            $bucket = $firebase->createStorage()->getBucket();

            $firebaseFilePath = 'users/photo_' . uniqid() . '_' . $file->getClientOriginalName();

            $bucket->upload(
                fopen($file->getRealPath(), 'r'),
                ['name' => $firebaseFilePath]
            );

            $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";
        }

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
                'photo' => $url
            ]
        );

        return response()->json([
            'message' => 'Teacher account created successfully!',
            'idnumber' => $personalInfo->idnumber
        ], 201);
    }

    public function deleteTeacher($idnumber)
    {
        try {
            $deleted = User::where('idnumber', $idnumber)->delete();

            if ($deleted) {
                return response()->json(['message' => 'Teacher deleted successfully.']);
            } else {
                return response()->json(['error' => 'Teacher not found.'], 404);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => 'Deletion failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function updateTeacherInfo(Request $request, $idnumber)
    {
        // Validate input fields, photo must be an image file (optional)
        $validator = Validator::make($request->all(), [
            'firstname' => 'nullable|string',
            'lastname' => 'nullable|string',
            'phone' => 'nullable|string',
            'gender' => 'nullable|in:male,female,other',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string',
            'photo' => 'nullable|file|image|max:5120',  // max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $teacher = Teachers::where('idnumber', $idnumber)->first();

        if (!$teacher) {
            return response()->json(['message' => 'Teacher info not found.'], 404);
        }

        $updateData = $request->only([
            'firstname',
            'lastname',
            'phone',
            'gender',
            'birthdate',
            'address',
        ]);

        // Remove empty string fields so they don't overwrite existing data with blank
        foreach ($updateData as $key => $value) {
            if ($value === '') {
                unset($updateData[$key]);
            }
        }

        // Handle photo upload if exists
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');

            $factory  = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
            $firebase = $factory->create();
            $bucket = $firebase->createStorage()->getBucket();

            $firebaseFilePath = 'users/photo_' . uniqid() . '_' . $file->getClientOriginalName();

            $bucket->upload(
                fopen($file->getRealPath(), 'r'),
                ['name' => $firebaseFilePath]
            );

            $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";

            // Add photo URL to update data
            $updateData['photo'] = $url;
        }

        // Update teacher model with validated & prepared data
        $teacher->update($updateData);

        return response()->json(['message' => 'Teacher personal info updated successfully.']);
    }




    public function changePassword(Request $request, $idnumber)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('idnumber', $idnumber)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Check email matches user
        if ($request->email !== $user->email) {
            return response()->json(['message' => 'Email does not match user.'], 400);
        }

        // Verify OTP
        $cachedOtp = Cache::get("otp_{$request->email}");
        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 401);
        }

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 403);
        }

        // Update password
        $user->password = bcrypt($request->new_password);
        $user->save();

        // Remove used OTP
        Cache::forget("otp_{$request->email}");

        return response()->json(['message' => 'Password changed successfully.']);
    }


    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string', // or 'email' if you're using email
            'password' => 'required|string',
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|string', // or 'email' if you're using email
            'password' => 'required|string',
        ]);

        // If validation fails, return the errors
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Try to find the user by username
        $user = User::where('email', $credentials['email'])->first();

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

        switch ($user->usertype) {
        case 'Administrator':
            $personalInfo = Admin::where('idnumber', $user->idnumber)->first();
            break;
        case 'Teacher':
            $personalInfo = Teachers::where('idnumber', $user->idnumber)->first();
            break;
        case 'Student':
            $personalInfo = Students::where('idnumber', $user->idnumber)->first();
            break;
        default:
            // Optionally handle unexpected usertype
            $personalInfo = null;
    }

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
            'personal_info' => $personalInfo
        ]);
    }


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

    public function getCurrentUser(Request $request)
    {
        // Fetch all TeacherClass records
        $user = $request->user(); // Automatically resolved from token
        return response()->json($user);
    }

    public function checkAuthStatus(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            switch ($user->usertype) {
                case 'Administrator':
                    $personalInfo = Admin::where('idnumber', $user->idnumber)->first();
                    break;
                case 'Teacher':
                    $personalInfo = Teachers::where('idnumber', $user->idnumber)->first();
                    break;
                case 'Student':
                    $personalInfo = Students::where('idnumber', $user->idnumber)->first();
                    break;
                default:
                    // Optionally handle unexpected usertype
                    $personalInfo = null;
            }
            return response()->json(['logged_in' => true, 'user' => Auth::user(), 'personal_info' => $personalInfo]);
        }

        return response()->json(['logged_in' => false], 401);
    }

}
