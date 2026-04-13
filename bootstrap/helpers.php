<?php

use App\Models\Preference;

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
