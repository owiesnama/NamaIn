<?php

namespace App\Services\Sync\Push;

use App\Enums\MutationOutcome;
use App\Exceptions\Sync\RejectedMutation;
use App\Models\Device;
use App\Models\User;
use App\Services\Sync\IdempotencyOutcome;
use App\Services\Sync\IdempotentMutation;
use App\Services\Sync\PublicIdResolver;
use Closure;
use DomainException;
use Illuminate\Support\Facades\Auth;

/**
 * The push engine (Design 02 §5.2): applies an ordered mutation batch, each
 * mutation in its own transaction via {@see IdempotentMutation} so one rejection
 * never rolls back earlier successes and a within-batch forward reference (a
 * public_id minted by an earlier mutation) resolves because that mutation has
 * already committed. Results are returned positionally aligned with the input.
 */
class PushProcessor
{
    public function __construct(
        private IdempotentMutation $idempotent,
        private PublicIdResolver $resolver,
        private MutationHandlerRegistry $handlers,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rawMutations
     * @return list<array<string, mixed>>
     */
    public function process(array $rawMutations, Device $device): array
    {
        return array_map(
            fn (array $raw): array => $this->processOne(PushMutation::fromArray($raw), $device),
            $rawMutations,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function processOne(PushMutation $mutation, Device $device): array
    {
        try {
            $actor = $this->resolveActor($mutation, $device);
            $handler = $this->handlers->for($mutation->type);

            $outcome = $this->actingAs($actor, fn (): IdempotencyOutcome => $this->idempotent->run(
                $device->tenant_id,
                $mutation->idempotencyKey,
                $mutation->type->value,
                fn (): array => $handler->handle($mutation, $actor, $device),
                $device->id,
            ));

            return $this->success($mutation, $outcome);
        } catch (RejectedMutation $rejection) {
            return $this->rejected($mutation, $rejection);
        } catch (DomainException $violation) {
            // A domain precondition (closed session, missing customer on a
            // credit sale) is a terminal rejection, never a 500 that fails the
            // whole push. The transaction already rolled back — zero writes.
            return $this->rejected($mutation, RejectedMutation::validationFailed($violation->getMessage()));
        }
    }

    /**
     * Resolve the mutation's actor — the device authenticates the channel; the
     * user attributes the work (Design 02 §1.2). The actor must belong to the
     * device's tenant.
     */
    private function resolveActor(PushMutation $mutation, Device $device): User
    {
        $actorId = $this->resolver->id(User::class, $mutation->actorPublicId);
        $actor = $actorId === null ? null : User::find($actorId);

        if ($actor === null || ! $actor->belongsToTenant($device->tenant)) {
            throw RejectedMutation::tenantMismatch(__('The mutation actor does not belong to this organization.'));
        }

        return $actor;
    }

    /**
     * Run a mutation as its actor so the reused domain Actions attribute the
     * work to the user (`created_by`, treasury actor, change-log actor) instead
     * of the authenticated device. The actor is set on the web guard and made
     * the default only for the duration of the closure — the sync guard is left
     * untouched, so it still re-resolves the device from the token per request.
     *
     * @template T
     *
     * @param  Closure(): T  $run
     * @return T
     */
    private function actingAs(User $actor, Closure $run): mixed
    {
        $previousGuard = Auth::getDefaultDriver();

        Auth::guard('web')->setUser($actor);
        Auth::shouldUse('web');

        try {
            return $run();
        } finally {
            Auth::shouldUse($previousGuard);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function success(PushMutation $mutation, IdempotencyOutcome $outcome): array
    {
        $body = $outcome->result ?? ['public_id' => $mutation->publicId, 'serial' => null];

        return array_merge([
            'idempotency_key' => $mutation->idempotencyKey,
            'outcome' => ($outcome->replayed ? MutationOutcome::AlreadyApplied : MutationOutcome::Applied)->value,
        ], $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function rejected(PushMutation $mutation, RejectedMutation $rejection): array
    {
        return [
            'idempotency_key' => $mutation->idempotencyKey,
            'outcome' => MutationOutcome::Rejected->value,
            'public_id' => $mutation->publicId,
            'serial' => null,
            'reason' => $rejection->reason->value,
            'message' => $rejection->getMessage(),
        ];
    }
}
