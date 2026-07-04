<?php

declare(strict_types=1);

use App\Http\Controllers\Sync\ProvisionController;
use App\Http\Middleware\Sync\BindDeviceTenant;
use App\Http\Middleware\Sync\EnsureDeviceActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sync API (/api/sync/v1) — Design 02
|--------------------------------------------------------------------------
| The offline device sync surface. The authenticated principal is a Device
| (sync guard), never a User; the tenant is bound from the device row.
*/

Route::post('/provision', ProvisionController::class)->name('provision');

Route::middleware(['auth:sync', EnsureDeviceActive::class, BindDeviceTenant::class])->group(function () {
    // Snapshot (PR-2) and pull (PR-3) endpoints register here.
});
