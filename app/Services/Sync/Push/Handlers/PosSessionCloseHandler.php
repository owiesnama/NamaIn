<?php

namespace App\Services\Sync\Push\Handlers;

use App\Actions\Pos\ReplayCloseSessionAction;
use App\Exceptions\Sync\RejectedMutation;
use App\Models\Device;
use App\Models\PosSession;
use App\Models\User;
use App\Services\Sync\PublicIdResolver;
use App\Services\Sync\Push\MutationHandler;
use App\Services\Sync\Push\PushMutation;

/**
 * `pos_session.close` (Design 02 §5.3, Design 04 §2.3): reconciles the device
 * register's drawer against the counted `closing_float` (integer minor units)
 * through {@see ReplayCloseSessionAction}, which resolves the drawer by
 * `register_id`, absorbs the variance into a drawer adjustment (unchanged cash
 * flow), and surfaces any offline-close variance to the reconciliation inbox.
 */
class PosSessionCloseHandler implements MutationHandler
{
    public function __construct(
        private ReplayCloseSessionAction $closeSession,
        private PublicIdResolver $resolver,
    ) {}

    public function handle(PushMutation $mutation, User $actor, Device $device): array
    {
        $sessionId = $this->resolver->id(PosSession::class, $mutation->payload['session'] ?? null);

        if ($sessionId === null) {
            throw RejectedMutation::unknownReference(__('The POS session to close has not synced yet.'));
        }

        $session = PosSession::findOrFail($sessionId);

        if (! $session->isOpen()) {
            throw RejectedMutation::sessionClosed();
        }

        $this->closeSession->handle(
            session: $session,
            closingFloat: (int) ($mutation->payload['closing_float'] ?? 0),
            actor: $actor,
            register: $device->register,
            device: $device,
            occurredAt: $mutation->occurredAt,
        );

        return ['public_id' => $session->public_id, 'serial' => null];
    }
}
