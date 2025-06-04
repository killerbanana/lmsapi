<?php

namespace App\Services;

use SendGrid\Mail\Mail;
use GuzzleHttp\Client;

class OtpEmailService
{
    protected $sendgrid;

    public function __construct()
    {
        // Create Guzzle client with SSL verification disabled (dev only)
        $guzzleClient = new Client([
            'verify' => false,
        ]);

        // Inject Guzzle client into SendGrid client via 'http_client' option
        $this->sendgrid = new \SendGrid(env('SENDGRID_API_KEY'), ['http_client' => $guzzleClient]);
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

            return [
                'success' => $response->statusCode() === 202,
                'message' => 'Sent',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
