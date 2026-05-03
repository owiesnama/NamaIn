<?php

namespace App\Actions\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthenticateTenantUser
{
    public function authenticate(Request $request, Tenant $tenant): User
    {
        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if (! $user->belongsToTenant($tenant)) {
            Auth::logout();
            $request->session()->invalidate();
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();
        $user->switchTenant($tenant);

        return $user;
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds]),
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return strtolower($request->input('email')).'|'.$request->ip();
    }
}
