<?php

namespace App\Services\Sync\Push;

use App\Exceptions\Sync\RejectedMutation;
use App\Models\Device;
use App\Models\User;
use App\Services\Sync\IdempotentMutation;

/**
 * Applies one push mutation type, reusing the existing domain Action so pushed
 * rows match cloud-created ones (Design 02 §5.2). Runs inside the per-mutation
 * transaction opened by {@see IdempotentMutation}; a handler
 * rejects by throwing {@see RejectedMutation}.
 *
 * The returned array becomes both the stored idempotency result and the wire
 * result body, so it must carry `public_id` (and `serial`, plus `flags` for a
 * sale) exactly as the device expects on replay.
 */
interface MutationHandler
{
    /**
     * @return array<string, mixed>
     */
    public function handle(PushMutation $mutation, User $actor, Device $device): array;
}
