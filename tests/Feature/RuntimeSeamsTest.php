<?php

use App\Http\Middleware\EnsureRuntimeIsOnline;
use App\Models\Role;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\JetstreamServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Support\Runtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = app('currentTenant');
    seedTenantRoles($this->tenant);

    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);
});

/*
|--------------------------------------------------------------------------
| S1 — config/runtime.php + App\Support\Runtime
|--------------------------------------------------------------------------
*/

test('the runtime profile defaults to cloud when RUNTIME_PROFILE is unset', function () {
    expect(config('runtime.profile'))->toBe('cloud')
        ->and(Runtime::isCloud())->toBeTrue()
        ->and(Runtime::isLocal())->toBeFalse();
});

test('runtime reports local when the profile is local', function () {
    config(['runtime.profile' => 'local']);

    expect(Runtime::isLocal())->toBeTrue()
        ->and(Runtime::isCloud())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| S4 — runtime.online middleware on online-only route groups
|--------------------------------------------------------------------------
*/

test('online-only routes 404 under the local profile', function (string $route) {
    config(['runtime.profile' => 'local']);

    $this->actingAs($this->owner)
        ->get(route($route))
        ->assertNotFound();
})->with([
    'purchases' => 'purchases.index',
    'stock transfers' => 'stock-transfers.index',
    'quotes' => 'quotes.index',
    'treasury' => 'treasury.index',
    'reports' => 'reports.index',
    'exports' => 'exports.index',
    'team' => 'users.index',
    'roles' => 'roles.index',
    'recurring expenses' => 'recurring-expenses.index',
]);

test('settings writes 404 under the local profile', function () {
    config(['runtime.profile' => 'local']);

    $this->actingAs($this->owner)
        ->post(route('preferences.update'), ['currency' => 'SDG'])
        ->assertNotFound();
});

test('online-only routes stay reachable under the default cloud profile', function (string $route) {
    $this->actingAs($this->owner)
        ->get(route($route))
        ->assertSuccessful();
})->with([
    'purchases' => 'purchases.index',
    'treasury' => 'treasury.index',
    'team' => 'users.index',
]);

test('the pos route resolves under the local profile', function () {
    config(['runtime.profile' => 'local']);

    // No sale point exists, so the POS page redirects to storages — the route
    // itself must resolve rather than 404 like the online-only groups.
    $this->actingAs($this->owner)
        ->get(route('pos.index'))
        ->assertRedirect(route('storages.index'));
});

/*
|--------------------------------------------------------------------------
| S5 — runtime Inertia shared prop
|--------------------------------------------------------------------------
*/

test('the runtime profile is shared as an inertia prop', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('runtime', 'cloud'));
});

/*
|--------------------------------------------------------------------------
| S2 — providers hook
|--------------------------------------------------------------------------
| The app-level Horizon and Telescope providers register only under the
| cloud profile. Their package providers remain auto-discovered on the
| cloud repo; the offline build excludes the packages at compose time.
*/

test('the providers list registers horizon and telescope only under the cloud profile', function () {
    $providersUnder = function (?string $profile): array {
        $previous = $_ENV['RUNTIME_PROFILE'] ?? null;

        if ($profile === null) {
            unset($_ENV['RUNTIME_PROFILE'], $_SERVER['RUNTIME_PROFILE']);
        } else {
            $_ENV['RUNTIME_PROFILE'] = $_SERVER['RUNTIME_PROFILE'] = $profile;
        }

        try {
            return require base_path('bootstrap/providers.php');
        } finally {
            if ($previous === null) {
                unset($_ENV['RUNTIME_PROFILE'], $_SERVER['RUNTIME_PROFILE']);
            } else {
                $_ENV['RUNTIME_PROFILE'] = $_SERVER['RUNTIME_PROFILE'] = $previous;
            }
        }
    };

    expect($providersUnder(null))
        ->toContain(HorizonServiceProvider::class)
        ->toContain(TelescopeServiceProvider::class);

    expect($providersUnder('local'))
        ->not->toContain(HorizonServiceProvider::class)
        ->not->toContain(TelescopeServiceProvider::class)
        ->toContain(AppServiceProvider::class)
        ->toContain(FortifyServiceProvider::class)
        ->toContain(JetstreamServiceProvider::class);
});

/*
|--------------------------------------------------------------------------
| S2 + S3 — RUNTIME_PROFILE=local boot smoke test
|--------------------------------------------------------------------------
| Boot the application in a separate process under the local profile and
| inspect the registered routes: tenant routes mount without a domain
| constraint, the admin surface is absent, and Horizon is not registered.
*/

test('the application boots under RUNTIME_PROFILE=local without the admin surface', function () {
    $process = new Process(
        command: ['php', '-d', 'display_errors=0', 'artisan', 'route:list', '--json'],
        cwd: base_path(),
        env: [
            'RUNTIME_PROFILE' => 'local',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'TELESCOPE_ENABLED' => 'false',
        ],
    );

    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

    $output = $process->getOutput();
    $routes = collect(json_decode(substr($output, (int) strpos($output, '[')), true));
    $names = $routes->pluck('name')->filter();

    // The POS surface resolves, without any tenant subdomain constraint.
    $pos = $routes->firstWhere('name', 'pos.index');
    expect($pos)->not->toBeNull()
        ->and($pos['domain'] ?? null)->toBeNull();

    // The admin surface is absent.
    expect($names->contains('admin.dashboard'))->toBeFalse()
        ->and($names->contains('welcome'))->toBeFalse();

    // Online-only groups keep their routes registered — they are gated by
    // the runtime.online middleware (hidden, not broken).
    $purchases = $routes->firstWhere('name', 'purchases.index');
    expect($purchases)->not->toBeNull()
        ->and($purchases['middleware'] ?? [])->toContain(EnsureRuntimeIsOnline::class);
});

test('the application boots under the cloud profile with tenant routes on the subdomain', function () {
    $process = new Process(
        command: ['php', '-d', 'display_errors=0', 'artisan', 'route:list', '--json'],
        cwd: base_path(),
        env: [
            'RUNTIME_PROFILE' => 'cloud',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'TELESCOPE_ENABLED' => 'false',
        ],
    );

    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

    $output = $process->getOutput();
    $routes = collect(json_decode(substr($output, (int) strpos($output, '[')), true));

    $pos = $routes->firstWhere('name', 'pos.index');
    expect($pos)->not->toBeNull()
        ->and($pos['domain'] ?? null)->not->toBeNull();

    expect($routes->firstWhere('name', 'admin.dashboard'))->not->toBeNull();
});
