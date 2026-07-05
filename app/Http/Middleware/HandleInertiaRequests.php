<?php

namespace App\Http\Middleware;

use App\Enums\NumeralSystem;
use App\Facades\Cache;
use App\Features\Facades\Entitlements;
use App\Models\Preference;
use App\Models\ReconciliationItem;
use App\Models\Tenant;
use App\Services\Utils\OperationFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs/shared-data
     */
    public function share(Request $request): array
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (! $tenant) {
            $tenant = $this->resolveTenantFromHost($request);
        }

        return array_merge(parent::share($request), [
            'appName' => config('app.name'),
            'appDomain' => config('app.domain'),
            'runtime' => config('runtime.profile'),
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'email_verified_at' => $request->user()->email_verified_at,
                'verified' => $request->user()->hasVerifiedEmail(),
                'profile_photo_url' => $request->user()->profile_photo_url,
                'role' => fn () => $request->user()->roleInCurrentTenant()?->only('id', 'name', 'slug'),
                'permissions' => fn () => $request->user()->roleInCurrentTenant()?->permissions->pluck('slug') ?? [],
            ] : null,
            'currentTenant' => $tenant
                ? ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug]
                : null,
            // Effective entitlements for the current tenant — boolean features and
            // limit caps only (no usage counts; those are fetched lazily per page).
            'entitlements' => ($tenant && $request->user())
                ? fn () => Entitlements::for($tenant)->toArray()
                : null,
            'tenants' => fn () => $request->user()
                ? $request->user()->tenants()->get(['tenants.id', 'name', 'slug'])
                : [],
            'jetstream' => [
                'managesProfilePhotos' => true,
            ],
            'preferences' => $tenant
                ? $this->resolvePreferences()
                : [],
            'userPreferences' => fn () => $request->user()?->user_preferences ?? [],
            'operations' => fn () => $this->operations($request),
            'unreadNotifications' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'reconciliationOpenCount' => fn () => $this->reconciliationOpenCount($request),
            'flash' => session()->get('flash') ?? [
                'success' => session()->get('success'),
                'error' => session()->get('error'),
                'response' => session()->get('response'),
                'travel_buffer_warnings' => session()->get('travel_buffer_warnings'),
            ],
            'isSuperAdmin' => fn () => Auth::guard('admin')->check(),
            'isImpersonating' => fn () => (bool) session('impersonating_from'),
            'impersonatingTenant' => fn () => session('impersonating_tenant_name'),
            'locale' => function () {
                return app()->getLocale();
            },
            'translations' => function () {
                $locale = app()->getLocale();

                return cache()->rememberForever("translations.{$locale}", fn () => $this->getJsonFileContent(
                    base_path("lang/{$locale}.json")
                ));
            },
        ]);
    }

    private function resolveTenantFromHost(Request $request): ?Tenant
    {
        $domain = config('app.domain');
        $host = $request->getHost();

        if (! str_ends_with($host, '.'.$domain)) {
            return null;
        }

        $slug = str_replace('.'.$domain, '', $host);

        if (! $slug || $slug === $host) {
            return null;
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if ($tenant) {
            app()->instance('currentTenant', $tenant);
            URL::defaults(['tenant' => $slug]);
        }

        return $tenant;
    }

    private function resolvePreferences(): array
    {
        $prefs = Cache::rememberForever('preferences', fn () => Preference::asPairs()->toArray());

        if (! empty($prefs['logo']) && ! str_starts_with($prefs['logo'], 'http') && ! str_starts_with($prefs['logo'], '/')) {
            $prefs['logo'] = asset('storage/'.$prefs['logo']);
        }

        // A tenant that has never chosen a numeral system follows its language,
        // so the frontend always receives a concrete value and never has to
        // re-derive the default.
        if (empty($prefs['numerals'])) {
            $prefs['numerals'] = NumeralSystem::defaultForLocale(app()->getLocale())->value;
        }

        return $prefs;
    }

    private function operations(Request $request): array
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (! $tenant || ! $request->user()) {
            return [];
        }

        return app(OperationFeed::class)->forUser($request->user(), $tenant);
    }

    /**
     * The open reconciliation-item count for the nav badge (Design 04 §3.2).
     * Permission-gated, tenant-scoped, and defensive against the pre-migration
     * window so it never errors on non-tenant or unauthenticated requests.
     */
    private function reconciliationOpenCount(Request $request): int
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $user = $request->user();

        if (! $tenant || ! $user || ! $user->hasPermission('reconciliation.view')) {
            return 0;
        }

        if (! Schema::hasTable('reconciliation_items')) {
            return 0;
        }

        return ReconciliationItem::open()->count();
    }

    public function getJsonFileContent($path)
    {
        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true);
    }
}
