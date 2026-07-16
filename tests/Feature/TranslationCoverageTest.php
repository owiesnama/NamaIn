<?php

use Symfony\Component\Finder\Finder;

/**
 * Every user-facing string in the Vue layer is wrapped in __(); this asserts the
 * matching Arabic value actually exists. Wrapping without a translation renders
 * the English key as a silent fallback — invisible to code review, which is how
 * 170 keys accumulated before this gate. A new __('...') with no lang/ar.json
 * entry fails here.
 */
it('has an Arabic translation for every static __() key in the Vue layer', function () {
    $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true);

    // A single-line quoted literal that is the first argument to __(), ending at
    // , (the __('key', { …interpolation }) form) or ). No DOTALL, so it never
    // crosses newlines; the trailing [,)] excludes __('a' + b) concatenation
    // because a "+" follows the quote — those keys are dynamic and unresolvable.
    $pattern = '/__\(\s*([\'"])((?:\\\\.|(?!\1)[^\n])*)\1\s*[,)]/';

    $missing = [];

    foreach (Finder::create()->files()->in(resource_path('js'))->name('*.vue') as $file) {
        preg_match_all($pattern, $file->getContents(), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            // Unescape the way a JS parser does, so the key matches the runtime
            // lookup ("account\'s" in source is "account's" at runtime).
            $key = str_replace(['\\\'', '\\"', '\\\\'], ["'", '"', '\\'], $match[2]);

            if ($key === '' || array_key_exists($key, $translations)) {
                continue;
            }

            $missing[$key] = $file->getRelativePathname();
        }
    }

    expect($missing)->toBe(
        [],
        "Untranslated __() keys (add to lang/ar.json):\n".
        collect($missing)->map(fn ($file, $key) => "  \"{$key}\"  ({$file})")->implode("\n")
    );
});
