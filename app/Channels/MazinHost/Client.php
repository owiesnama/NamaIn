<?php

namespace App\Channels\MazinHost;

use Illuminate\Support\Facades\Http;

class Client
{
    protected $apiKey;

    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.mazin_host.api_key');
        $this->baseUrl = 'https://mazinhost.com/smsv1/sms/api?action=send-sms';
    }

    public function send(Message $message, $to)
    {
        $requestData = array_merge($message->toArray(), [
            'from' => config('services.mazin_host.sender_id'),
            'api_key' => $this->apiKey,
            'to' => $to,
        ]);

        $response = Http::post($this->baseUrl, $requestData);

        return $response;
    }
}
