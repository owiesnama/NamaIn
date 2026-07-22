<?php

namespace App\Enums;

enum SyncSnapshotStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
