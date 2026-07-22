<?php

namespace App\Http\Middleware\Sync;

use App\Models\Device;
use App\Services\Sync\SyncLogContext;
use App\Services\Sync\SyncLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-request sync audit logging (Design 02 §8.1). Runs inside the authed group
 * so the device is bound; endpoints declare their own fields (cursors, counts,
 * client_pushed_at) in the `sync_log` attribute bag and this measures latency
 * and resolves the endpoint token, then writes one row.
 */
class RecordSyncLog
{
    public function __construct(
        private SyncLogger $logger,
        private SyncLogContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $this->context->reset();

        $response = $next($request);

        /** @var Device|null $device */
        $device = $request->user('sync');

        if ($device !== null) {
            $this->logger->record(
                $device,
                $this->endpoint($request),
                (int) round((microtime(true) - $startedAt) * 1000),
                $this->context->fields(),
            );
        }

        return $response;
    }

    /**
     * The endpoint token (fits `sync_logs.endpoint`, 24 chars): the first
     * segment of the route name, so `snapshot.store` logs as `snapshot`.
     */
    private function endpoint(Request $request): string
    {
        $name = $request->route()?->getName() ?? 'sync.unknown';

        return explode('.', str_replace('sync.', '', $name))[0];
    }
}
