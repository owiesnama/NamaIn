<?php

use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertSame;

/**
 * The non-blocking email-verification banner strings must be translatable.
 * English renders via the source key; Arabic must resolve from lang/ar.json.
 */
$bannerKeys = [
    'Please verify your email address',
    'Your account is active. Verifying your email keeps it secure and makes sure you receive important notifications.',
    'Resend email',
    'Email sent',
    'Verification email sent. Check your inbox.',
    'Dismiss',
];

it('renders the verify-email banner strings in english by key', function () use ($bannerKeys) {
    app()->setLocale('en');

    foreach ($bannerKeys as $key) {
        assertSame($key, __($key));
    }
});

it('localizes the verify-email banner strings in arabic', function () use ($bannerKeys) {
    app()->setLocale('ar');

    foreach ($bannerKeys as $key) {
        $translated = __($key);
        assertNotSame($key, $translated, "Missing Arabic translation for: {$key}");
        expect($translated)->toMatch('/\p{Arabic}/u');
    }
});
