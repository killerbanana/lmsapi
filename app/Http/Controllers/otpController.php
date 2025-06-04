<?php

namespace App\Http\Controllers;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Services\OtpEmailService;
use Illuminate\Http\Request;

class otpController extends Controller
{
    public function sendOtp(Request $request, OtpEmailService $otpEmailService)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string'
        ]);

        $email = $request->input('email');
        $name = $request->input('name', 'User');
        $otp = rand(100000, 999999);

        Cache::put("otp_{$email}", $otp, now()->addMinutes(5));

        $sent = $otpEmailService->sendOtp($email, $name, $otp);

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'OTP sent successfully' : 'Failed to send OTP'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $email = $request->input('email');
        $otp = $request->input('otp');

        $cachedOtp = Cache::get("otp_{$email}");

        if ($cachedOtp && $cachedOtp == $otp) {
            Cache::forget("otp_{$email}");
            return response()->json(['success' => true, 'message' => 'OTP verified']);
        }

        return response()->json(['success' => false, 'message' => 'Invalid or expired OTP']);
    }
}
