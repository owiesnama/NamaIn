<?php

namespace App\Http\Controllers\Sync;

use App\Enums\MutationOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\PushRequest;
use App\Models\Device;
use App\Services\Sync\ClockSkew;
use App\Services\Sync\Push\PushProcessor;
use App\Services\Sync\SyncLogContext;
use App\Services\Sync\SyncProtocol;
use Illuminate\Http\JsonResponse;

/**
 * POST /push (Design 02 §5) — ability sync:push. Applies the mutation batch
 * synchronously, each mutation its own transaction, and answers one result per
 * mutation positionally aligned with the request. Records the device-reported
 * backlog and the audit fields (§8.1, §8.2, §8.5).
 */
class PushController extends Controller
{
    public function __invoke(PushRequest $request, PushProcessor $processor, SyncLogContext $log): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sync');

        $results = $processor->process($request->validated('mutations'), $device);

        $device->forceFill(array_merge(
            ['last_push_at' => now()],
            $this->reportedBacklog($request),
        ))->saveQuietly();

        $log->set($this->auditFields($request, $results));

        return response()->json([
            'results' => $results,
            'protocol' => SyncProtocol::VERSION,
        ]);
    }

    /**
     * The device-reported outbox backlog and running version, carried on every
     * push (Design 02 §8.2, §8.5) since the cloud cannot observe it.
     *
     * @return array<string, mixed>
     */
    private function reportedBacklog(PushRequest $request): array
    {
        return array_filter([
            'pending_count' => $request->input('pending_count'),
            'oldest_pending_at' => $request->input('oldest_pending_at'),
            'app_version' => $request->input('app_version'),
            'clock_skew_seconds' => ClockSkew::fromRequest($request),
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    private function auditFields(PushRequest $request, array $results): array
    {
        $applied = collect($results)->whereIn('outcome', [
            MutationOutcome::Applied->value,
            MutationOutcome::AlreadyApplied->value,
        ])->count();

        return [
            'mutation_count' => count($results),
            'applied_count' => $applied,
            'rejected_count' => count($results) - $applied,
            'client_pushed_at' => $request->input('client_pushed_at'),
        ];
    }
}
