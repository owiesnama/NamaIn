<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ClearTenantDataAction;
use App\Actions\Admin\LogAdminAction;
use App\Actions\Admin\RestoreTenantDataAction;
use App\Enums\TenantDataGroup;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantDataController extends Controller
{
    public function __construct(private LogAdminAction $logger) {}

    public function clear(Request $request, Tenant $tenant, ClearTenantDataAction $action): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        $validated = $request->validate([
            'domain_confirmation' => ['required', 'string', Rule::in([$tenant->slug])],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['required', Rule::enum(TenantDataGroup::class)],
        ], [
            'domain_confirmation.in' => __('The domain confirmation does not match.'),
        ]);

        $groups = array_map(fn (string $v) => TenantDataGroup::from($v), $validated['groups']);

        $action->handle($tenant, $groups);

        $this->logger->handle($request->user()->id, 'tenant.data_cleared', $tenant, [
            'groups' => $validated['groups'],
        ]);

        return back()->with('success', __('Tenant data has been cleared. Hard delete scheduled in 30 days.'));
    }

    public function restore(Request $request, Tenant $tenant, RestoreTenantDataAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $action->handle($tenant);

        $this->logger->handle($request->user()->id, 'tenant.data_restored', $tenant);

        return back()->with('success', __('Tenant data has been restored.'));
    }
}
