<?php


namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client(
            env('TWILIO_SID'),
            env('TWILIO_TOKEN')
        );
    }

    public function sendSMS($to, $message)
    {
        return $this->client->messages->create($to, [
            'from' => env('TWILIO_FROM'),
            'body' => $message,
        ]);
    }

    public function sendWhatsApp($to, $message)
    {
        return $this->client->messages->create("whatsapp:$to", [
            'from' => env('TWILIO_WHATSAPP_FROM'),
            'body' => $message,
        ]);
    }
}
