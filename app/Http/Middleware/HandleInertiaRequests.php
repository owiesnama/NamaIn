<?php

namespace App\Http\Middleware;

use App\Models\Preference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private function tenantCacheKey(string $key): string
    {
        $tenantId = app()->has('currentTenant') ? app('currentTenant')->id : 0;

        return "tenant_{$tenantId}_{$key}";
    }

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
     * @see https://inertiajs.com/shared-data
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'appName' => config('app.name'),
            'appDomain' => config('app.domain'),
            'user' => $request->user() ? [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'profile_photo_url' => $request->user()->profile_photo_url,
            ] : null,
            'currentTenant' => $request->user()?->currentTenant
                ? ['id' => $request->user()->currentTenant->id, 'name' => $request->user()->currentTenant->name, 'slug' => $request->user()->currentTenant->slug]
                : null,
            'jetstream' => [
                'managesProfilePhotos' => true,
            ],
            'preferences' => Cache::rememberForever($this->tenantCacheKey('preferences'), fn () => Preference::asPairs()),
            'flash' => [
                'success' => session()->get('success'),
                'error' => session()->get('error'),
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
