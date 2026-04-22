<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
        ]);

        URL::defaults(['tenant' => 'test-org']);
    }

    protected bool $withTenantSubdomain = true;

    protected function prepareUrlForRequest($uri): string
    {
        $uri = parent::prepareUrlForRequest($uri);

        if (! $this->withTenantSubdomain) {
            return $uri;
        }

        // Don't rewrite if already pointing to tenant subdomain
        if (str_contains($uri, 'test-org.')) {
            return $uri;
        }

        $tenantBase = 'http://test-org.'.config('app.domain');
        $appUrl = config('app.url');

        if ($appUrl && str_contains($uri, $appUrl)) {
            $uri = str_replace($appUrl, $tenantBase, $uri);
        }

        if (str_contains($uri, 'http://localhost')) {
            $uri = str_replace('http://localhost', $tenantBase, $uri);
        }

        return $uri;
    }

    public function actingAs(Authenticatable $user, $guard = null): static
    {
        $this->ensureTenantContext($user);

        return parent::actingAs($user, $guard);
    }

    public function signIn($user = null): static
    {
        return $this->actingAs($user ?? User::factory()->create());
    }

    public function withoutTenantSubdomain(): static
    {
        $this->withTenantSubdomain = false;

        return $this;
    }

    protected function ensureTenantContext(Authenticatable $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        // If user already has a tenant set, respect it
        if ($user->current_tenant_id) {
            $tenant = $user->currentTenant;
            app()->instance('currentTenant', $tenant);

            return;
        }

        $tenant = Tenant::where('slug', 'test-org')->first();

        if (! $tenant) {
            $tenant = Tenant::create(['name' => 'Test Org', 'slug' => 'test-org', 'is_active' => true]);
        }

        if (! $user->belongsToTenant($tenant)) {
            $tenant->users()->attach($user, ['role' => 'owner']);
            $user->unsetRelation('tenants');
        }

        $user->update(['current_tenant_id' => $tenant->id]);
        $user->refresh();

        app()->instance('currentTenant', $tenant);
    }
}
