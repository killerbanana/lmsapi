<?php

namespace App\Http\Controllers;

use App\Models\PersonalInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PersonalInfoController extends Controller
{
    // List all personal info records
    public function index()
    {
        return response()->json(PersonalInfo::all(), 200);
    }

    // Show a single personal info record by ID
    public function show($id)
    {
        $info = PersonalInfo::find($id);
        if (!$info) {
            return response()->json(['message' => 'Personal info not found'], 404);
        }

        return response()->json($info, 200);
    }

    // Store new personal info
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idnumber' => 'required|unique:personal_info,idnumber',
            'firstname' => 'nullable|string',
            'lastname' => 'nullable|string',
            'email' => 'required|email|unique:personal_info,email',
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

        $info = PersonalInfo::create($request->all());

        return response()->json(['message' => 'Personal info created', 'data' => $info], 201);
    }

    // Update personal info
    public function update(Request $request, $id)
    {
        $info = PersonalInfo::find($id);
        if (!$info) {
            return response()->json(['message' => 'Personal info not found'], 404);
        }

        $info->update($request->all());

        return response()->json(['message' => 'Personal info updated', 'data' => $info], 200);
    }

    // Delete personal info
    public function destroy($id)
    {
        $info = PersonalInfo::find($id);
        if (!$info) {
            return response()->json(['message' => 'Personal info not found'], 404);
        }

        $info->delete();

        return response()->json(['message' => 'Personal info deleted'], 200);
    }
}
