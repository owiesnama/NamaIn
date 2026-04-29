<?php

namespace App\Events;

use App\Models\ExportLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExportStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ExportLog $exportLog) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("exports.user.{$this->exportLog->user_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->exportLog->id,
            'export_key' => $this->exportLog->export_key,
            'status' => $this->exportLog->status->value,
            'filename' => $this->exportLog->filename,
            'failure_message' => $this->exportLog->failure_message,
        ];
    }
}
