<?php

use Symfony\Component\Finder\Finder;

/**
 * All money formatting goes through resources/js/Support/money.js, which reads
 * the tenant's numeral system and isolates Latin output for the RTL layout. A
 * bespoke Intl.NumberFormat elsewhere silently ignores the numerals preference
 * and, for Latin output, reintroduces the "-20 renders as 20-" bidi bug. Before
 * this rule there were 40-odd such copies. Keep them from regrowing.
 */
it('has no bespoke Intl.NumberFormat outside Support/money.js', function () {
    $offenders = [];

    foreach (Finder::create()->files()->in(resource_path('js'))->name(['*.vue', '*.js']) as $file) {
        if ($file->getRelativePathname() === 'Support/money.js') {
            continue;
        }

        if (str_contains($file->getContents(), 'Intl.NumberFormat')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe(
        [],
        'Format money via useCurrency() / <Money> / window.formatMoney, not a bespoke '.
        "Intl.NumberFormat:\n  ".implode("\n  ", $offenders)
    );
});
