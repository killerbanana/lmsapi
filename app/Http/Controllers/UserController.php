<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\OtpEmailService;
use SendGrid;
use SendGrid\Mail\Mail;

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
            'guardian_contact' => 'nullable|string',
            'guardian_name' => 'nullable|string',
            'photo' => 'nullable|file|image|max:5120',
            'primary_email' => 'required|email|unique:users,email|different:email',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');

            $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
            $bucket = $firebase->createStorage()->getBucket();

            $firebaseFilePath = 'users/photo_' . uniqid() . '_' . $file->getClientOriginalName();

            $bucket->upload(
                fopen($file->getRealPath(), 'r'),
                ['name' => $firebaseFilePath]
            );

            $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";
        }

        DB::beginTransaction();

        try {
            $studentPassword = Str::random(8); // Generate random password for student
            $studentUser = User::create([
                'username' => $request->username,
                'idnumber' => $request->idnumber,
                'email' => $request->email,
                'password' => bcrypt($studentPassword),
                'usertype' => 'Student',
            ]);

            // Send welcome email to student
            $this->sendWelcomeEmail($request->email, $studentPassword);

            Students::updateOrCreate(
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
                    'guardian_contact' => $request->guardian_contact,
                    'guardian_name' => $request->guardian_name,
                    'primary_email' => $request->primary_email,
                ]
            );

            $guardianId = $studentUser->idnumber . '-parent';
            $guardianPassword = Str::random(8);

            User::create([
                'username' => $guardianId,
                'idnumber' => $guardianId,
                'email' => $request->primary_email,
                'password' => bcrypt($guardianPassword),
                'usertype' => 'Parent',
            ]);
            
            // Send welcome email to guardian
            $this->sendWelcomeEmail($request->primary_email, $guardianPassword);

            ParentModel::create([
                'idnumber' => $guardianId,
                'firstname' => $request->mothername ?? '',
                'lastname' => $request->lastname ?? '',
                'email' => $request->primary_email,
                'phone' => $request->mothercontact ?? '',
                'linked_id' => $studentUser->idnumber,
                'guardian_name' => $request->guardian_name,
                'photo' => $url,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Student and guardian accounts created successfully!',
                'idnumber' => $studentUser->idnumber,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student Registration Failed: ' . $e->getMessage());
            return response()->json(['error' => 'Registration failed. Please try again later.', 'details' => $e->getMessage()], 500);
        }
    }

    public function sendWelcomeEmail($emailTo, $password)
    {
        $apiKey = config('services.sendgrid.api_key');
        $sendgrid = new SendGrid($apiKey);
        $email = new Mail();

        $email->setFrom("rosqueta.joshua@gmail.com", "LMS Admin");
        $email->setSubject("Welcome to LMS!");
        $email->addTo($emailTo, 'user');

        $plainTextContent = "Hello,\n\n"
            . "Welcome to the Learning Management System. Your account has been created.\n\n"
            . "Here are your login credentials:\n"
            . "Email: {$emailTo}\n"
            . "Password: {$password}\n\n"
            . "Please change your password after your first login.\n\n"
            . "Best regards,\n"
            . "LMS Admin Team";

        $email->addContent("text/plain", $plainTextContent);

        try {
            $sendgrid->send($email);
        } catch (\Exception $e) {
            Log::error('SendGrid Exception: ' . $e->getMessage());
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

    public function updateStatus(Request $request, $idnumber)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:active,inactive,blocked',
            'user_type' => 'required|in:student,teacher',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->user_type === "student") {
            $student = Students::where('idnumber', $idnumber)->first();

            if (!$student) {
                return response()->json(['message' => 'Student not found.'], 404);
            }

            $student->status = $request->status;

            if ($student->save()) {
                return response()->json(['message' => 'Student status updated.']);
            } else {
                return response()->json(['message' => 'Failed to update student.'], 500);
            }
        } else { // user_type is 'teacher'
            $teacher = Teachers::where('idnumber', $idnumber)->first();

            if (!$teacher) {
                return response()->json(['message' => 'Teacher not found.'], 404);
            }

            $teacher->status = $request->status;

            if ($teacher->save()) {
                return response()->json(['message' => 'Teacher status updated.']);
            } else {
                return response()->json(['message' => 'Failed to update teacher.'], 500);
            }
        }
    }


    public function updateStudentInfo(Request $request, $idnumber)
    {
        // Validate input
        $url = null;
        $validator = Validator::make($request->all(), [
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
            'status' => 'nullable|in:active,inactive,blocked',
            'guardian_contact' => 'nullable|string',
            'guardian_name' => 'nullable|string',
            'photo' => 'nullable|file|image|max:5120', // optional photo
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Find the student by idnumber
        $student = Students::where('idnumber', $idnumber)->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        // Handle photo upload if exists
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');

            $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
            $bucket = $firebase->createStorage()->getBucket();

            $firebaseFilePath = 'users/photo_' . uniqid() . '_' . $file->getClientOriginalName();

            $bucket->upload(
                fopen($file->getRealPath(), 'r'),
                ['name' => $firebaseFilePath]
            );

            $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";
        }

        Students::where('idnumber', $idnumber)->update([
            'firstname' =>  $request->firstname,
            'lastname' => $request->lastname,
            'phone' => $request->phone,
            'gender' => $request->gender,
            'birthdate' => $request->birthdate,
            'address' => $request->address,
            'fathername' => $request->fathername,
            'fathercontact' => $request->fathercontact,
            'mothername' => $request->mothername,
            'mothercontact' => $request->mothercontact,
            'guardian_contact' => $request->guardian_contact,
            'guardian_name' => $request->guardian_name,
            'photo' => $url
        ]);

        if ($student->save()) {
            return response()->json([
                'message' => 'Student info updated successfully.',
            ]);
        } else {
            return response()->json(['message' => 'Failed to update student.'], 500);
        }
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
        $password = Str::random(8); // Generate a random password

        $user = User::create([
            'username' => $request->username,
            'idnumber' => $request->idnumber,
            'email' => $request->email,
            'password' => bcrypt($password),
            'usertype' => 'Teacher',
        ]);

        // Send welcome email to teacher
        $this->sendWelcomeEmail($request->email, $password);

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');

            $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
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
        $user = Auth::user();
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

        switch ($user->usertype) {
            case 'Administrator':
                $data = [
                    'firstname' => $request->firstname,
                    'lastname' => $request->lastname,
                    'phone' => $request->phone,
                    'gender' => $request->gender,
                    'birthdate' => $request->birthdate,
                    'address' => $request->address,
                ];

                // Handle photo upload if exists
                if ($request->hasFile('photo')) {
                    $file = $request->file('photo');

                    $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
                    $bucket = $firebase->createStorage()->getBucket();

                    $firebaseFilePath = 'users/photo_' . uniqid() . '_' . $file->getClientOriginalName();

                    $bucket->upload(
                        fopen($file->getRealPath(), 'r'),
                        ['name' => $firebaseFilePath]
                    );

                    $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";
                    $data['photo'] = $url; // only set photo if uploaded
                }

                // Update teacher
                $updated = Teachers::where('idnumber', $idnumber)->update($data);

                if ($updated) {
                    return response()->json([
                        'message' => 'Teacher info updated successfully.',
                    ]);
                } else {
                    return response()->json(['message' => 'Failed to update teacher.'], 500);
                }
                break;
            case 'Teacher':
                if ($user->idnumber === $idnumber) {
                    $data = [
                        'firstname' => $request->firstname,
                        'lastname' => $request->lastname,
                        'phone' => $request->phone,
                        'gender' => $request->gender,
                        'birthdate' => $request->birthdate,
                        'address' => $request->address,
                    ];

                    // Handle photo upload if exists
                    if ($request->hasFile('photo')) {
                        $file = $request->file('photo');

                        $firebase = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
                        $bucket = $firebase->createStorage()->getBucket();

                        $firebaseFilePath = 'users/photo_' . uniqid() . '_' . $file->getClientOriginalName();

                        $bucket->upload(
                            fopen($file->getRealPath(), 'r'),
                            ['name' => $firebaseFilePath]
                        );

                        $url = "https://firebasestorage.googleapis.com/v0/b/" . $bucket->name() . "/o/" . urlencode($firebaseFilePath) . "?alt=media";
                        $data['photo'] = $url; // only set photo if uploaded
                    }

                    // Update teacher
                    $updated = Teachers::where('idnumber', $idnumber)->update($data);

                    if ($updated) {
                        return response()->json([
                            'message' => 'Teacher info updated successfully.',
                        ]);
                    } else {
                        return response()->json(['message' => 'Failed to update teacher.'], 500);
                    }
                } else {
                    return response()->json(['message' => 'Unathorized to update profile.'], 403);
                }

                break;
            case 'Student':
                $personalInfo = Students::where('idnumber', $user->idnumber)->first();
                break;
            default:
                // Optionally handle unexpected usertype
                $personalInfo = null;
        }
    }


    public function forgotPassword(Request $request)
    {
        // 1. Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // If validation fails, return the errors
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // 2. Find the user by the provided email address
        // We use the email from the request instead of an ID number from the URL.
        $user = User::where('email', $request->email)->first();

        // If no user is found with that email, return an error
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // 3. Verify the One-Time Password (OTP)
        // The OTP is retrieved from the cache using the user's email as the key.
        $cachedOtp = Cache::get("otp_{$request->email}");
        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 401);
        }

        // 4. Update the user's password
        // The new password is encrypted before saving.
        $user->password = bcrypt($request->new_password);
        $user->save();

        // 5. Clean up by removing the used OTP from the cache
        Cache::forget("otp_{$request->email}");

        // 6. Return a success response
        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function changePassword(Request $request, $idnumber)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
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
        $idnumber = $request->query('idnumber');
        $firstname = $request->query('firstname');
        $lastname = $request->query('lastname');

        $query = Students::query();

        if ($idnumber) {
            $query->where('idnumber', 'LIKE', '%' . $idnumber . '%');
        }

        if ($firstname) {
            $query->where('firstname', 'LIKE', '%' . $firstname . '%');
        }

        if ($lastname) {
            $query->where('lastname', 'LIKE', '%' . $lastname . '%');
        }

        $paginated = $query->paginate($perPage);

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
        $perPage = $request->query('perPage', 10);
        $idnumber = $request->query('idnumber');
        $firstname = $request->query('firstname');
        $lastname = $request->query('lastname');

        $query = Teachers::query();

        if ($idnumber) {
            $query->where('idnumber', 'LIKE', '%' . $idnumber . '%');
        }

        if ($firstname) {
            $query->where('firstname', 'LIKE', '%' . $firstname . '%');
        }

        if ($lastname) {
            $query->where('lastname', 'LIKE', '%' . $lastname . '%');
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'teachers' => $paginated->getCollection()->transform(function ($teacher) {
                return [
                    'id' => $teacher->id,
                    'idnumber' => $teacher->idnumber,
                    'firstname' => $teacher->firstname,
                    'lastname' => $teacher->lastname,
                    'phone' => $teacher->phone,
                    'gender' => $teacher->gender,
                    'birthdate' => $teacher->birthdate,
                    'address' => $teacher->address,
                    'photo' => $teacher->photo,
                    'email' => $teacher->email,
                    'created_at' => $teacher->created_at,
                    'updated_at' => $teacher->updated_at,
                    'username' => $teacher->user ? $teacher->user->username : null,
                ];
            }),
        ], 200);
    }

    public function getParents(Request $request)
    {
        $perPage = $request->query('perPage', 10);  // default 10 per page
        $idnumber = $request->query('idnumber');
        $firstname = $request->query('firstname');
        $lastname = $request->query('lastname');

        $query = ParentModel::query();

        if ($idnumber) {
            $query->where('idnumber', 'LIKE', '%' . $idnumber . '%');
        }

        if ($firstname) {
            $query->where('firstname', 'LIKE', '%' . $firstname . '%');
        }

        if ($lastname) {
            $query->where('lastname', 'LIKE', '%' . $lastname . '%');
        }

        $paginated = $query->paginate($perPage);

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
