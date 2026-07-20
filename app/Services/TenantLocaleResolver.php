<?php

declare(strict_types=1);

namespace App\Services;

use App\Facades\Cache;
use App\Models\Preference;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class TenantLocaleResolver
{
    /**
     * Ensure the application locale reflects the tenant/user preference.
     *
     * Error responses can bypass ResolveTenant — an unmatched 404 never runs
     * the route middleware, so the tenant is never resolved and the app stays
     * on the fallback locale, rendering error pages in the wrong language and
     * LTR. This re-resolves the locale from the bound tenant, or, when nothing
     * is bound, from the request subdomain.
     */
    public static function ensureResolved(Request $request): void
    {
        if (! App::isLocale(config('app.locale'))) {
            return; // Already resolved by ResolveTenant.
        }

        $tenant = app()->bound('currentTenant')
            ? app('currentTenant')
            : Tenant::where('slug', Str::before($request->getHost(), '.'))->first();

        if (! $tenant instanceof Tenant) {
            return;
        }

        app()->instance('currentTenant', $tenant);

        $preferences = Cache::rememberForever('preferences', fn () => Preference::asPairs()->toArray());
        $locale = $preferences['language'] ?? config('app.locale');

        $userLocale = $request->user()?->user_preferences['language'] ?? null;

        App::setLocale($userLocale ?? $locale);
    }

    /**
     * Locale-dependent Inertia props needed to render a standalone page (e.g. the
     * error page) in the resolved locale. Error responses on unmatched routes skip
     * HandleInertiaRequests, so the shared `locale`/`translations` props are absent
     * and must be provided explicitly for the page to render translated and RTL.
     *
     * @return array{locale: string, translations: array<string, string>}
     */
    public static function inertiaLocaleProps(): array
    {
        $locale = App::getLocale();

        return [
            'locale' => $locale,
            'translations' => cache()->rememberForever(
                "translations.{$locale}",
                fn () => static::translationsFor($locale),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function translationsFor(string $locale): array
    {
        $path = base_path("lang/{$locale}.json");

        return is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
    }
}
