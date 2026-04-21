<?php

use App\Models\Preference;

if (! function_exists('tenant_route')) {
    /**
     * Generate a URL for a named route scoped to a tenant subdomain.
     *
     * Works like Laravel's route() helper but prepends the tenant subdomain.
     *
     * @param  array<string, mixed>  $parameters
     */
    function tenant_route(string $name, string $tenantSlug, array $parameters = [], bool $absolute = true): string
    {
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';
        $baseDomain = config('app.domain');

        URL::defaults(['tenant' => $tenantSlug]);

        $path = route($name, $parameters, false);

        URL::defaults(['tenant' => null]);

        if (! $absolute) {
            return $path;
        }

        return "{$scheme}://{$tenantSlug}.{$baseDomain}{$path}";
    }
}

if (! function_exists('preference')) {
    function preference($key, $default = null)
    {
        $value = Preference::where('key', $key)->first()?->value;

        if ($key === 'logo' && $value && ! str_starts_with($value, 'http') && ! str_starts_with($value, '/')) {
            return asset('storage/'.$value);
        }

        return $value ?? $default;
    }
}
