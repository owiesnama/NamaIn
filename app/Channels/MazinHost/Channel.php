<?php

namespace App\Channels\MazinHost;

use Illuminate\Notifications\Notification;

class Channel
{
    const COUNTRY_CODE = 249;

    public $client;

    public function __construct()
    {
        $this->client = new Client;
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $this->client->send(
            $notification->toMazinHost($notifiable),
            $this->to($notifiable, $notification)
        );
    }

    public function to($notifiable, $notification)
    {
        if (method_exists($notifiable, 'routeNotificationForMazinHost')) {
            $to = $notifiable->routeNotificationForMazinHost($notification);
        }
        $to = $notifiable->phone_number;

        return static::COUNTRY_CODE . ltrim($to);
    }
}
