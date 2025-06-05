<?php

namespace App\Services;

use SendGrid\Mail\Mail;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OtpEmailService
{
    protected $sendgrid;

    public function __construct()
    {
        // ❗ Avoid disabling SSL in production
        $guzzleClient = new Client([
            'verify' => env('SENDGRID_SSL_VERIFY', true),
        ]);

        $this->sendgrid = new \SendGrid(env('SENDGRID_API_KEY'), [
            'http_client' => $guzzleClient,
        ]);
    }

    public function sendOtp(string $toEmail, string $toName, string $otp): array
    {
        $email = new Mail();
        $email->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
        $email->setSubject('Your OTP Code');
        $email->addTo($toEmail, $toName);

        $htmlContent = "
            <h1>Your OTP Code</h1>
            <p>Your OTP is: <strong>{$otp}</strong></p>
            <p>This code is valid for 5 minutes.</p>
        ";

        $email->addContent("text/html", $htmlContent);

        try {
            $response = $this->sendgrid->send($email);

            // Log full response for debugging
            Log::info('SendGrid OTP Email Sent', [
                'to' => $toEmail,
                'status' => $response->statusCode(),
                'body' => $response->body(),
            ]);

            return [
                'success' => $response->statusCode() === 202,
                'message' => $response->statusCode() === 202 ? 'OTP sent successfully' : 'SendGrid did not accept the message.',
            ];
        } catch (\Exception $e) {
            Log::error('SendGrid OTP Email Failed', [
                'to' => $toEmail,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'SendGrid error: ' . $e->getMessage(),
            ];
        }
    }
}
