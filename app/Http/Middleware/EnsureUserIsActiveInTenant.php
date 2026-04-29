<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActiveInTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session('impersonating_from')) {
            return $next($request);
        }

        $user = $request->user();
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($user && $tenant && ! $user->isActiveInTenant($tenant)) {
            auth('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')
                ->with('error', __('Your access to this organization has been disabled.'));
        }

        return $next($request);
    }
}
