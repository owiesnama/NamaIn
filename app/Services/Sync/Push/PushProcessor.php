<?php

namespace App\Services\Sync\Push;

use App\Actions\Reconciliation\RaiseReconciliationItem;
use App\Enums\MutationOutcome;
use App\Enums\ReconciliationType;
use App\Exceptions\Sync\RejectedMutation;
use App\Models\ChangeLog;
use App\Models\Device;
use App\Models\ParkedMutation;
use App\Models\User;
use App\Services\Sync\IdempotencyOutcome;
use App\Services\Sync\IdempotentMutation;
use App\Services\Sync\PublicIdResolver;
use Carbon\CarbonImmutable;
use Closure;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

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
        private RaiseReconciliationItem $raiseReconciliationItem,
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
            $this->parkIfTerminal($mutation, $device, $rejection);

            return $this->rejected($mutation, $rejection);
        } catch (DomainException $violation) {
            // A domain precondition (closed session, missing customer on a
            // credit sale) is a terminal rejection, never a 500 that fails the
            // whole push. The transaction already rolled back — zero writes.
            $rejection = RejectedMutation::validationFailed($violation->getMessage());
            $this->parkIfTerminal($mutation, $device, $rejection);

            return $this->rejected($mutation, $rejection);
        } catch (Throwable $e) {
            // Anything unexpected (a QueryException on production data, a
            // TypeError) is OUR fault, not the mutation's: report it, reject
            // THIS mutation retriably, keep the envelope alive. Field-caught: a
            // single 500 here froze a device's whole queue — no sale could sync
            // until an unrelated bug was fixed.
            report($e);

            return $this->rejected($mutation, RejectedMutation::serverError());
        }
    }

    /**
     * Park a *terminally* rejected mutation (Design 04 §1.2, §1.3): non-retriable
     * reasons store the raw envelope and raise a `ParkedMutation` inbox item in
     * their own tiny transaction (the rejected business mutation itself wrote
     * nothing). Retriable rejections are never parked — the device re-pushes once
     * the missing reference lands. The unique (tenant_id, idempotency_key) makes
     * a re-push of a still-broken mutation a no-op: the row is not re-created, so
     * no duplicate inbox item is raised.
     */
    private function parkIfTerminal(PushMutation $mutation, Device $device, RejectedMutation $rejection): void
    {
        if ($rejection->reason->isRetriable()) {
            return;
        }

        DB::transaction(function () use ($mutation, $device, $rejection): void {
            ChangeLog::lockTenant($device->tenant_id);

            $existing = ParkedMutation::where('tenant_id', $device->tenant_id)
                ->where('idempotency_key', $mutation->idempotencyKey)
                ->exists();

            if ($existing) {
                return;
            }

            $parked = ParkedMutation::create([
                'tenant_id' => $device->tenant_id,
                'device_id' => $device->id,
                'mutation_type' => $mutation->type->value,
                'idempotency_key' => $mutation->idempotencyKey,
                'rejection_reason' => $rejection->reason,
                'rejection_message' => $rejection->getMessage(),
                'envelope' => $this->envelope($mutation),
                'occurred_at' => $this->occurredAt($mutation),
            ]);

            $this->raiseReconciliationItem->for(
                subject: $parked,
                type: ReconciliationType::ParkedMutation,
                device: $device,
                register: $device->register,
                actor: null,
                occurredAt: $parked->occurred_at,
            );
        });
    }

    /**
     * The full mutation DTO as received, for audit/replay.
     *
     * @return array<string, mixed>
     */
    private function envelope(PushMutation $mutation): array
    {
        return [
            'idempotency_key' => $mutation->idempotencyKey,
            'type' => $mutation->type->value,
            'public_id' => $mutation->publicId,
            'actor' => $mutation->actorPublicId,
            'occurred_at' => $mutation->occurredAt,
            'payload' => $mutation->payload,
        ];
    }

    private function occurredAt(PushMutation $mutation): CarbonImmutable
    {
        return $mutation->occurredAt !== null
            ? CarbonImmutable::parse($mutation->occurredAt)
            : CarbonImmutable::now();
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
