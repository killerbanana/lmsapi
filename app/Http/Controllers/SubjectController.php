<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubjectController extends Controller
{
    public function createSubject(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|string|unique:subjects,subject_id',
            'name' => 'required|string|max:255',
        ]);

        // If validation fails, return the errors
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Retrieve validated data
        $validated = $validator->validated();

        // Create the new subject
        $subject = Subject::create([
            'subject_id' => $validated['subject_id'],
            'name' => $validated['name'],
        ]);

        // Return a JSON response
        return response()->json(['message' => 'Subject created successfully', 'subject' => $subject], 201);
    }
}
