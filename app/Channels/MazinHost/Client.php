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

    public function sms(Message $message, $to)
    {
        $requestData = array_merge($message->toArray(), [
            'from' => env('MAZIN_HOST_SENDER_ID'),
            'api_key' => $this->apiKey,
            'to' => $to,
        ]);
        dump($requestData);
        $response = Http::post($this->baseUrl, $requestData);
        dump($response->body());

        return $response;
    }
}
