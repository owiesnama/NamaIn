<?php

declare(strict_types=1);

use App\Http\Controllers\Sync\AttachmentController;
use App\Http\Controllers\Sync\DetachController;
use App\Http\Controllers\Sync\HeartbeatController;
use App\Http\Controllers\Sync\ProvisionController;
use App\Http\Controllers\Sync\PullController;
use App\Http\Controllers\Sync\PushController;
use App\Http\Controllers\Sync\SnapshotController;
use App\Http\Middleware\Sync\BindDeviceTenant;
use App\Http\Middleware\Sync\EnsureDeviceActive;
use App\Http\Middleware\Sync\RecordSyncLog;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sync API (/api/sync/v1) — Design 02
|--------------------------------------------------------------------------
| The offline device sync surface. The authenticated principal is a Device
| (sync guard), never a User; the tenant is bound from the device row.
*/

Route::post('/provision', ProvisionController::class)->name('provision');

Route::middleware(['auth:sync', EnsureDeviceActive::class, BindDeviceTenant::class, RecordSyncLog::class])->group(function () {
    Route::get('/pull', PullController::class)
        ->middleware('abilities:sync:pull')
        ->name('pull');

    Route::post('/push', PushController::class)
        ->middleware('abilities:sync:push')
        ->name('push');

    Route::post('/attachments', AttachmentController::class)
        ->middleware('abilities:sync:attach')
        ->name('attachments.store');

    Route::post('/heartbeat', HeartbeatController::class)->name('heartbeat');

    Route::post('/detach', DetachController::class)->name('detach');

    Route::middleware('abilities:sync:snapshot')->group(function () {
        Route::post('/snapshot', [SnapshotController::class, 'store'])->name('snapshot.store');
        Route::get('/snapshot/{snapshotId}', [SnapshotController::class, 'show'])->name('snapshot.show');
        Route::get('/snapshot/{snapshotId}/manifest', [SnapshotController::class, 'manifest'])->name('snapshot.manifest');
        Route::get('/snapshot/{snapshotId}/download', [SnapshotController::class, 'download'])->name('snapshot.download');
    });
});
