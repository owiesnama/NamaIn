<?php

namespace App\Services\Sync\Push\Handlers;

use App\Actions\Pos\OpenPosSessionAction;
use App\Exceptions\Sync\RejectedMutation;
use App\Models\Device;
use App\Models\User;
use App\Services\Sync\Push\MutationHandler;
use App\Services\Sync\Push\PushMutation;

/**
 * `pos_session.open` (Design 02 §5.3): opens the session on the device's own
 * register and sale-point storage (never the payload's), so the opening float
 * lands in the register-linked drawer via the shared DrawerResolver. The
 * device-minted session public_id is preserved for later `sale.create` refs.
 * `opening_float` is integer minor units on the wire and in storage alike.
 */
class PosSessionOpenHandler implements MutationHandler
{
    public function __construct(private OpenPosSessionAction $openSession) {}

    public function handle(PushMutation $mutation, User $actor, Device $device): array
    {
        $register = $device->register;
        $storage = $register?->storage;

        if ($storage === null) {
            throw RejectedMutation::unknownReference(__('The device register has no sale point.'));
        }

        $session = $this->openSession->handle(
            storage: $storage,
            openingFloat: (int) ($mutation->payload['opening_float'] ?? 0),
            actor: $actor,
            register: $register,
            sessionPublicId: $mutation->publicId,
        );

        return ['public_id' => $session->public_id, 'serial' => null];
    }
}
