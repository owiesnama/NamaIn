<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TenantSelectionController extends Controller
{
    public function index(Request $request): Response|RedirectResponse|HttpResponse
    {
        $tenants = $request->user()->tenants()->get(['tenants.id', 'name', 'slug']);

        if ($tenants->count() === 1) {
            return $this->redirectToTenant($request, $tenants->first());
        }

        return Inertia::render('Tenants/Select', [
            'tenants' => $tenants,
        ]);
    }

    public function select(Request $request, Tenant $tenant): RedirectResponse|HttpResponse
    {
        $request->user()->switchTenant($tenant);

        return $this->redirectToTenant($request, $tenant);
    }

    private function redirectToTenant(Request $request, Tenant $tenant): RedirectResponse|HttpResponse
    {
        $request->user()->switchTenant($tenant);

        $targetUrl = route('dashboard', ['tenant' => $tenant->slug]);

        if ($request->header('X-Inertia')) {
            return Inertia::location($targetUrl);
        }

        return redirect()->away($targetUrl);
    }
}
