<?php

namespace App\Actions\Pos;

use App\Models\PosSession;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OpenPosSessionAction
{
    public function execute(Storage $storage, int $openingFloat, User $actor): PosSession
    {
        return DB::transaction(function () use ($storage, $openingFloat, $actor) {
            // Lock storage row to prevent race condition on opening multiple sessions
            $storage = Storage::where('id', $storage->id)->lockForUpdate()->first();

            if ($storage->active_session_id) {
                throw new \DomainException('Storage already has an active POS session.');
            }

            $session = PosSession::create([
                'tenant_id' => $storage->tenant_id,
                'storage_id' => $storage->id,
                'opened_by' => $actor->id,
                'opening_float' => $openingFloat,
            ]);

            $storage->update([
                'active_session_id' => $session->id,
            ]);

            return $session;
        });
    }
}
