<?php

namespace App\Channels\MazinHost;

use Carbon\Carbon;

class Message
{
    public $content;

    public $schedule;

    public $unicode;

    public function __construct($message = null)
    {
        $this->content = $message;
    }

    public function content($message)
    {
        $this->content = $message;

        return $this;
    }

    public function schedule(Carbon $datatime)
    {
        $this->schedule = $datatime;

        return $this;
    }

    public function unicode(bool $enable = true)
    {
        $this->unicode = $enable;

        return $this;
    }

    public function toArray()
    {
        return [
            'sms' => $this->content,
            'unicode' => $this->unicode,
            'schedule' => $this->schedule,
        ];
    }
}
