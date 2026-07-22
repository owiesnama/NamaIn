<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\AttachmentRequest;
use App\Services\Sync\SyncProtocol;
use App\Services\Sync\SyncReceiptStore;
use Illuminate\Http\JsonResponse;

/**
 * POST /attachments (Design 02 §7.4) — ability sync:attach. A binary receipt
 * uploaded out of band from the push JSON and linked to its expense by
 * `receipt_public_id`, so large files retry independently and may precede or
 * follow the expense mutation.
 */
class AttachmentController extends Controller
{
    public function __invoke(AttachmentRequest $request, SyncReceiptStore $store): JsonResponse
    {
        $receiptPublicId = $request->string('receipt_public_id')->value();

        $store->store($request->file('file'), $receiptPublicId);

        return response()->json([
            'stored' => true,
            'receipt_public_id' => $receiptPublicId,
            'protocol' => SyncProtocol::VERSION,
        ], 201);
    }
}
