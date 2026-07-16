<?php

declare(strict_types=1);

use App\Facades\Cache;
use App\Models\Preference;
use App\Services\TenantLocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Simulate production, where the app fallback locale (en) differs from the
    // tenant's configured language (ar). Error responses must still render in ar.
    config(['app.locale' => 'en']);
    App::setLocale('en');
    Cache::forget('preferences');
});

it('renders the 404 error page in the tenant locale with translations, not the app fallback', function () {
    Preference::create(['key' => 'language', 'value' => 'ar']);

    $this->get('/__missing-route-'.uniqid(), ['Accept' => 'text/html'])
        ->assertStatus(404)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('locale', 'ar')
            ->where('translations.Page not found', 'الصفحة غير موجودة'));
});

it('resolves the locale from the request subdomain when no tenant is bound', function () {
    $tenant = app('currentTenant');
    Preference::create(['key' => 'language', 'value' => 'ar']);

    // Simulate an unmatched 404: ResolveTenant never ran, so nothing is bound.
    app()->forgetInstance('currentTenant');

    TenantLocaleResolver::ensureResolved(
        Request::create("http://{$tenant->slug}.namain.test/whatever")
    );

    expect(App::getLocale())->toBe('ar');
});
