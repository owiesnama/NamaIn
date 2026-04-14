<?php

use App\Channels\MazinHost\Channel;
use App\Channels\MazinHost\Client;
use App\Channels\MazinHost\Message;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

test('mazinhost message toArray works', function () {
    $message = new Message('Hello world');
    $message->unicode(true)->schedule($now = now());

    expect($message->toArray())->toBe([
        'sms' => 'Hello world',
        'unicode' => true,
        'schedule' => $now,
    ]);
});

test('mazinhost client sends request', function () {
    Http::fake();

    $client = new Client;
    $message = new Message('Test message');
    $to = '249123456789';

    $client->send($message, $to);

    Http::assertSent(function ($request) use ($to) {
        return $request->url() === 'https://mazinhost.com/smsv1/sms/api?action=send-sms' &&
               $request['sms'] === 'Test message' &&
               $request['to'] === $to;
    });
});

test('mazinhost channel sends notification', function () {
    Http::fake();

    $channel = new Channel;

    $notifiable = new class
    {
        public $phone_number = '123456789';
    };

    $notification = new class extends Notification
    {
        public function toMazinHost($notifiable)
        {
            return new Message('Notification content');
        }
    };

    $channel->send($notifiable, $notification);

    Http::assertSent(function ($request) {
        return $request['sms'] === 'Notification content' &&
               $request['to'] === '249123456789';
    });
});

test('mazinhost channel uses routeNotificationForMazinHost if exists', function () {
    Http::fake();

    $channel = new Channel;

    $notifiable = new class
    {
        public $phone_number = '123456789';

        public function routeNotificationForMazinHost()
        {
            return '987654321';
        }
    };

    $notification = new class extends Notification
    {
        public function toMazinHost($notifiable)
        {
            return new Message('Content');
        }
    };

    $channel->send($notifiable, $notification);

    Http::assertSent(function ($request) {
        return $request['to'] === '249987654321';
    });
});
