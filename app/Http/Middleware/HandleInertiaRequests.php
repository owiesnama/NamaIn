<?php

namespace App\Http\Middleware;

use App\Models\Preference;
use App\Services\TenantCache;
use Illuminate\Http\Request;
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

        return array_merge(parent::share($request), [
            'appName' => config('app.name'),
            'appDomain' => config('app.domain'),
            'user' => $request->user() ? [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'profile_photo_url' => $request->user()->profile_photo_url,
            ] : null,
            'currentTenant' => $tenant
                ? ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug]
                : null,
            'tenants' => fn () => $request->user()
                ? $request->user()->tenants()->get(['tenants.id', 'name', 'slug'])
                : [],
            'jetstream' => [
                'managesProfilePhotos' => true,
            ],
            'preferences' => $tenant
                ? TenantCache::rememberForever('preferences', fn () => Preference::asPairs())
                : [],
            'flash' => [
                'success' => session()->get('success'),
                'error' => session()->get('error'),
                'response' => session()->get('response'),
            ],
            'locale' => function () {
                return app()->getLocale();
            },
            'translations' => function () {
                return $this->getJsonFileContent(
                    base_path('lang/'.app()->getLocale().'.json')
                );
            },
        ]);
    }

    public function getJsonFileContent($path)
    {
        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true);
    }
}
