<?php

namespace App\Channels\MazinHost;

use Carbon\Carbon;

class Message
{
    /**
     * @var mixed
     */
    public $content;

    /**
     * @var Carbon
     */
    public $schedule;

    /**
     * @var bool
     */
    public $unicode;

    /**
     * @param  mixed  $message
     * @return void
     */
    public function __construct($message = null)
    {
        $this->content = $message;
    }

    /**
     * @param  mixed  $message
     * @return $this
     */
    public function content(string $message)
    {
        $this->content = $message;

        return $this;
    }

    /**
     * @return $this
     */
    public function schedule(Carbon $datatime)
    {
        $this->schedule = $datatime;

        return $this;
    }

    /**
     * @return $this
     */
    public function unicode(bool $enable = true)
    {
        $this->unicode = $enable;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'sms' => $this->content,
            'unicode' => $this->unicode,
            'schedule' => $this->schedule,
        ];
    }
}
