<?php

namespace App\Actions\Pos;

use App\Models\PosSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClosePosSessionAction
{
    public function execute(PosSession $session, int $closingFloat, User $actor): void
    {
        if (! $session->isOpen()) {
            throw new \DomainException('POS session is already closed.');
        }

        DB::transaction(function () use ($session, $closingFloat, $actor) {
            $session->update([
                'closed_by' => $actor->id,
                'closing_float' => $closingFloat,
                'closed_at' => now(),
            ]);

            $session->storage->update([
                'active_session_id' => null,
            ]);
        });
    }
}
