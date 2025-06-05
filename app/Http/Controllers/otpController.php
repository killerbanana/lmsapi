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

        Cache::put("otp_{$emailTo}", $otp, now()->addMinutes(5));

        $sendgrid = new \SendGrid(env('SENDGRID_API_KEY'));

        $emailMessage = new \SendGrid\Mail\Mail();
        $emailMessage->setFrom("rosqueta.joshua@gmail.com", "LMS ADMIN");
        $emailMessage->setSubject("OTP Code");
        $emailMessage->addTo($emailTo, $name);  // use $emailTo here, not $emailMessage
        $emailMessage->addContent("text/plain", "Your OTP is: {$otp}");

        try {
            $response = $sendgrid->send($emailMessage);

            return response()->json([
                'success' => $response->statusCode() === 202,
                'message' => $response->statusCode() === 202 ? 'OTP sent successfully' : 'Failed to send OTP',
                'statusCode' => $response->statusCode(),
                'responseBody' => $response->body(),
            ]);
        } catch (\Exception $e) {
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
