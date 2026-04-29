<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class TenantStatusController extends Controller
{
    public function update(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->isActive() ? $tenant->deactivate() : $tenant->activate();

        $status = $tenant->isActive() ? __('activated') : __('deactivated');

        return back()->with('success', __('Tenant :status successfully.', ['status' => $status]));
    }
}
