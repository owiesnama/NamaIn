<?php

namespace App\Channels\MazinHost;

use Illuminate\Support\Facades\Http;

class Client
{
    protected $apiKey;

    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('MAZIN_HOST_SMS_API_KEY');
        $this->baseUrl = 'https://mazinhost.com/smsv1/sms/api?action=send-sms';
    }

    public function send(Message $message, $to)
    {
        $requestData = array_merge($message->toArray(), [
            'from' => env('MAZIN_HOST_SENDER_ID'),
            'api_key' => $this->apiKey,
            'to' => $to,
        ]);

        $response = Http::post($this->baseUrl, $requestData);

        return $response;
    }
}
