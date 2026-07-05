<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\PushRequest;
use App\Models\Device;
use App\Services\Sync\Push\PushProcessor;
use App\Services\Sync\SyncProtocol;
use Illuminate\Http\JsonResponse;

/**
 * POST /push (Design 02 §5) — ability sync:push. Applies the mutation batch
 * synchronously, each mutation its own transaction, and answers one result per
 * mutation positionally aligned with the request.
 */
class PushController extends Controller
{
    public function __invoke(PushRequest $request, PushProcessor $processor): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sync');

        $results = $processor->process($request->validated('mutations'), $device);

        $device->forceFill(['last_push_at' => now()])->saveQuietly();

        return response()->json([
            'results' => $results,
            'protocol' => SyncProtocol::VERSION,
        ]);
    }
}
