<?php

namespace App\Events;

use App\Models\ImportLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ImportLog $importLog) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("operations.user.{$this->importLog->user_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->importLog->id,
            'import_type' => $this->importLog->import_type,
            'status' => $this->importLog->status->value,
            'rows_imported' => $this->importLog->rows_imported,
            'failure_message' => $this->importLog->failure_message,
        ];
    }
}
