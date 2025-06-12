<?php

namespace App\Services;

use Twilio\Rest\Client;

class SmsService
{
    protected $twilio;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');

        \Log::info("SID: $sid");
        \Log::info("TOKEN: $token");
        

        $this->twilio = new Client($sid, $token);
    }

    public function send(string $to, string $message)
    {
        $from = config('services.twilio.from');
        \Log::info("FROM: " . config('services.twilio.from'));
        return $this->twilio->messages->create($to, [
            'from' => $from,
            'body' => $message,
        ]);
    }
}

