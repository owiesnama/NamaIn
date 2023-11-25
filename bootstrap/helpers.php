<?php

use App\Models\Preference;

if (! function_exists('preference')) {
    function preference($key, $default = null)
    {
        $value = Preference::where('key', $key)->first()?->value;

        return $value ?? $default;
    }
}
