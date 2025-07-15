<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Services\OtpEmailService;
use Illuminate\Http\Request;

class otpController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string'
        ]);

        $emailTo = $request->input('email');
        $name = $request->input('name', 'User');
        $otp = rand(100000, 999999);


        $apiKey = config('services.sendgrid.api_key');

        // if (!$apiKey) {
        //     return response()->json(['error' => 'SendGrid API key not set']);
        // }
        Cache::put("otp_{$emailTo}", $otp, now()->addMinutes(5));

        $sendgrid = new \SendGrid($apiKey);

        $emailMessage = new \SendGrid\Mail\Mail();
        $emailMessage->setFrom("jaysonviernes@gmail.com", "iACLMS-admin");
        $emailMessage->setSubject("Password Change Request");
        $emailMessage->addTo($emailTo, $name);

        $plainTextContent = "Hello {$name},\n\n"
            . "We received a request to change your password. Your One-Time Password (OTP) is:\n\n"
            . "{$otp}\n\n"
            . "This code is valid for the next 5 minutes.\n\n"
            . "If you did not request a password change, please ignore this email or contact support immediately.\n\n"
            . "Best regards,\n"
            . "LMS Admin Team";

        $emailMessage->addContent("text/plain", $plainTextContent);

        try {
            $response = $sendgrid->send($emailMessage);

            return response()->json([
                'success' => $response->statusCode() === 202,
                'message' => $response->statusCode() === 202 ? 'OTP sent successfully!' : 'Failed to send OTP',
                'statusCode' => $response->statusCode(),
                'responseBody' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::create([
                'level' => 'error',
                'message' => 'Failed to send email',
                'context' => [
                    'error' => $e->getMessage(),
                ]
            ]);
            return response()->json([
                'success' => false,
                'message' => 'SendGrid Exception: ' . $e->getMessage(),
            ]);
        }
    }

    public function sendOtpEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $emailTo = $request->input('email');
        $otp = rand(100000, 999999);


        $apiKey = config('services.sendgrid.api_key');
        
        Cache::put("otp_{$emailTo}", $otp, now()->addMinutes(5));

        $sendgrid = new \SendGrid($apiKey);

        $emailMessage = new \SendGrid\Mail\Mail();
        $emailMessage->setFrom("jaysonviernes@gmail.com", "iACLMS-admin");
        $emailMessage->setSubject("Forgot Password Request");
        $emailMessage->addTo($emailTo, 'user');

        $plainTextContent = "Hello {$emailTo},\n\n"
            . "We received a forgot password request. Your One-Time Password (OTP) is:\n\n"
            . "{$otp}\n\n"
            . "This code is valid for the next 5 minutes.\n\n"
            . "If you did not request for forogt password, please ignore this email or contact support immediately.\n\n"
            . "Best regards,\n"
            . "LMS Admin Team";

        $emailMessage->addContent("text/plain", $plainTextContent);

        try {
            $response = $sendgrid->send($emailMessage);

            return response()->json([
                'success' => $response->statusCode() === 202,
                'message' => $response->statusCode() === 202 ? 'OTP sent successfully!' : 'Failed to send OTP',
                'statusCode' => $response->statusCode(),
                'responseBody' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::create([
                'level' => 'error',
                'message' => 'Failed to send email',
                'context' => [
                    'error' => $e->getMessage(),
                ]
            ]);
            return response()->json([
                'success' => false,
                'message' => 'SendGrid Exception: ' . $e->getMessage(),
            ]);
        }
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
