<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BackupsController as AdminBackupsController;
use App\Http\Controllers\Admin\BackupSettingsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\TenantInvitationsController;
use App\Http\Controllers\Admin\TenantOwnershipController;
use App\Http\Controllers\Admin\TenantsController as AdminTenantsController;
use App\Http\Controllers\Admin\TenantStatusController;
use App\Http\Controllers\Admin\TenantUserRoleController;
use App\Http\Controllers\Admin\TenantUsersController;
use App\Http\Controllers\Admin\TenantUserStatusController;
use App\Http\Controllers\Core\TenantSelectionController;
use App\Http\Controllers\Users\InvitationController;
use App\Http\Middleware\EnsureSuperAdmin;
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
Route::view('/offline', 'offline');
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept.store');

/*
|--------------------------------------------------------------------------
| Super-Admin Routes (Main Domain Only)
|--------------------------------------------------------------------------
| These routes are only reachable on the main domain. Tenant subdomains
| ({tenant}.domain) are routed via routes/tenant.php in bootstrap/app.php
| and never fall through to web.php, so no domain constraint is needed.
*/
Route::prefix('__admin')->name('admin.')->group(function () {
    // Guest routes (login)
    Route::get('login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AdminAuthController::class, 'login']);

    // Authenticated admin routes
    Route::middleware(['auth:admin', EnsureSuperAdmin::class])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity');

        Route::resource('tenants', AdminTenantsController::class)->except(['create', 'edit']);
        Route::put('tenants/{tenant}/status', [TenantStatusController::class, 'update'])->name('tenants.status');
        Route::put('tenants/{tenant}/ownership', [TenantOwnershipController::class, 'update'])->name('tenants.ownership');

        Route::post('tenants/{tenant}/users', [TenantUsersController::class, 'store'])->name('tenants.users.store');
        Route::delete('tenants/{tenant}/users/{user}', [TenantUsersController::class, 'destroy'])->name('tenants.users.destroy');
        Route::put('tenants/{tenant}/users/{user}/role', [TenantUserRoleController::class, 'update'])->name('tenants.users.role');
        Route::put('tenants/{tenant}/users/{user}/status', [TenantUserStatusController::class, 'update'])->name('tenants.users.status');

        Route::post('tenants/{tenant}/invitations', [TenantInvitationsController::class, 'store'])->name('tenants.invitations.store');
        Route::delete('tenants/{tenant}/invitations/{invitation}', [TenantInvitationsController::class, 'destroy'])->name('tenants.invitations.destroy');

        Route::resource('backups', AdminBackupsController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::put('backups/settings', [BackupSettingsController::class, 'update'])->name('backups.settings');

        Route::post('tenants/{tenant}/users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('impersonate.start');
    });

    // Stop impersonation — outside admin middleware since the user is on the web guard
    Route::middleware(['auth:sanctum'])
        ->post('impersonate/stop', [ImpersonationController::class, 'stop'])
        ->name('impersonate.stop');
});
