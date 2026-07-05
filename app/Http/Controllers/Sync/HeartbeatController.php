<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\HeartbeatRequest;
use App\Models\Device;
use App\Services\Sync\ClockSkew;
use App\Services\Sync\SyncProtocol;
use Illuminate\Http\JsonResponse;

/**
 * POST /heartbeat (Design 02 §8.2): a lightweight idle beat that refreshes the
 * device health columns PRD-04's dashboard reads — the reported backlog plus
 * the pilot SLO counters (§8.5). Written quietly: a beat must never spam the
 * change log. `last_seen_at` is refreshed by BindDeviceTenant on every request.
 */
class HeartbeatController extends Controller
{
    public function __invoke(HeartbeatRequest $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sync');

        $device->forceFill(array_filter([
            'pending_count' => $request->input('pending_count'),
            'oldest_pending_at' => $request->input('oldest_pending_at'),
            'app_version' => $request->input('app_version'),
            'crash_count' => $request->input('crash_count'),
            'session_count' => $request->input('session_count'),
            'clock_skew_seconds' => ClockSkew::fromRequest($request),
        ], fn ($value): bool => $value !== null))->saveQuietly();

        return response()->json([
            'protocol' => SyncProtocol::VERSION,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
