<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\SmsService;

class AnnouncementController extends Controller
{
    public function index()
    {
        return response()->json([
            'announcements' => Announcement::with('poster')->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'audience' => 'in:All,Students,Teachers,Admins',
            'published_at' => 'nullable|date',
            'status' => 'nullable|in:active,inactive',
        ]);

        $announcement = Announcement::create([
            'title' => $request->title,
            'body' => $request->body,
            'audience' => $request->audience ?? 'All',
            'published_at' => $request->published_at ?? now(),
            'status' => $request->status ?? 'active',
            'posted_by' => Auth::id(),
        ]);

        $announcement->load('poster');

        return response()->json([
            'message' => 'Announcement posted.',
            'announcement' => $announcement
        ]);
    }

    public function show($id)
    {
        $announcement = Announcement::with('poster')->find($id);

        if (!$announcement) {
            return response()->json([
                'message' => 'Announcement not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Announcement retrieved.',
            'data' => $announcement,
        ]);
    }

    public function update(Request $request, $id)
    {
        $announcement = Announcement::find($id);

        if (!$announcement) {
            return response()->json([
                'message' => 'Announcement not found.',
                'data' => null,
            ], 404);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'audience' => 'sometimes|in:All,Students,Teachers,Admins',
            'published_at' => 'nullable|date',
            'status' => 'nullable|in:active,inactive',
        ]);

        $announcement->update([
            'title' => $request->title ?? $announcement->title,
            'body' => $request->body ?? $announcement->body,
            'audience' => $request->audience ?? $announcement->audience,
            'published_at' => $request->published_at ?? $announcement->published_at,
            'status' => $request->status ?? $announcement->status,
        ]);

        $announcement->load('poster');

        return response()->json([
            'message' => 'Announcement updated.',
            'announcement' => $announcement
        ]);
    }

    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }

    public function sendSms(Request $request, SmsService $sms)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:160',
        ]);

        $to = $request->input('phone');
        $message = $request->input('message');

        $sms->send($to, $message);

        return response()->json(['message' => 'SMS sent successfully!']);
    }
    
}
