<?php

use App\Http\Controllers\Core\TenantSelectionController;
use App\Http\Controllers\Users\InvitationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes (Main Domain)
|--------------------------------------------------------------------------
|
| These routes are served on the main domain (e.g. nama-in.test).
| All tenant-scoped app routes live in routes/tenant.php on subdomains.
|
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('tenants.select');
    }

    return Inertia::render('Welcome', [
        'canLogin' => true,
        'canRegister' => true,
    ]);
})->name('welcome');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/tenants/select', [TenantSelectionController::class, 'index'])->name('tenants.select');
    Route::post('/tenants/{tenant}/select', [TenantSelectionController::class, 'select'])->name('tenants.switch');
});

/*
|--------------------------------------------------------------------------
| Invitation Routes (Public)
|--------------------------------------------------------------------------
*/
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept.store');
