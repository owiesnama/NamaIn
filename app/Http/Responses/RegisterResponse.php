<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        $user = $request->user()?->fresh(['currentTenant']);
        $tenant = $user?->currentTenant;

        if ($tenant) {
            return Inertia::location(tenant_route('dashboard', $tenant->slug));
        }

        return Inertia::location('/');
    }
}
