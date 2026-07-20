<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\LogAdminAction;
use App\Features\Facades\Entitlements;
use App\Features\Feature;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantFeatureOverrideController extends Controller
{
    public function __construct(private readonly LogAdminAction $logger) {}

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $data = $request->validate([
            'feature_key' => ['required', Rule::in(array_map(fn (Feature $f) => $f->value, Feature::cases()))],
            'value' => ['nullable'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $feature = Feature::from($data['feature_key']);
        $value = $feature->isBoolean()
            ? $request->boolean('value')
            : (($data['value'] ?? null) === null || $data['value'] === '' ? null : (int) $data['value']);

        // Upsert so a stale/expired override on the unique (tenant, feature) key
        // is replaced rather than causing a constraint violation.
        $tenant->featureOverrides()->updateOrCreate(
            ['feature_key' => $feature->value],
            ['value' => $value, 'expires_at' => $data['expires_at'] ?? null],
        );

        Entitlements::flush($tenant);

        $this->logger->handle($request->user()->id, 'tenant.override_set', $tenant, [
            'feature' => $feature->value,
        ]);

        return back()->with('success', __('Override saved successfully.'));
    }

    public function destroy(Request $request, Tenant $tenant, string $feature): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->featureOverrides()->where('feature_key', $feature)->delete();

        Entitlements::flush($tenant);

        $this->logger->handle($request->user()->id, 'tenant.override_removed', $tenant, [
            'feature' => $feature,
        ]);

        return back()->with('success', __('Override removed successfully.'));
    }
}
