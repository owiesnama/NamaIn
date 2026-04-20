<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $tenantDashboardUrl = route('dashboard', ['tenant' => $tenant->slug]);

            return $request->wantsJson()
                ? new JsonResponse(['redirect' => $tenantDashboardUrl])
                : redirect()->away($tenantDashboardUrl);
        }

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => '/'])
            : redirect('/');
    }
}
