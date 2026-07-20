<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\DeleteTenantAction;
use App\Actions\Admin\LogAdminAction;
use App\Actions\Admin\UpdateTenantAction;
use App\Actions\ProvisionTenantAction;
use App\Features\Facades\Entitlements;
use App\Features\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class TenantsController extends Controller
{
    public function __construct(private LogAdminAction $logger) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Tenant::class);

        $tenants = Tenant::query()
            ->withCount('users')
            ->when(request('search'), fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")))
            ->when(request('status') !== null, fn ($q) => $q->where('is_active', request('status') === 'active'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return inertia('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => request()->only('search', 'status'),
        ]);
    }

    public function store(StoreTenantRequest $request, ProvisionTenantAction $action): RedirectResponse
    {
        $this->authorize('create', Tenant::class);

        $owner = User::where('email', $request->owner_email)->firstOrFail();
        $tenant = $action->handle($request->name, $request->slug, $owner);

        $this->logger->handle($request->user()->id, 'tenant.created', $tenant, ['owner_email' => $owner->email]);

        return back()->with('success', __('Tenant created successfully.'));
    }

    public function show(Tenant $tenant): Response
    {
        $this->authorize('view', $tenant);

        $tenant->loadCount('users');

        $members = $tenant->users()
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo_url' => $user->profile_photo_url,
                'role' => $user->pivot->role,
                'role_id' => $user->pivot->role_id,
                'is_active' => (bool) $user->pivot->is_active,
                'must_change_password' => $user->must_change_password,
                'joined_at' => $user->pivot->created_at,
            ]);

        $roles = $tenant->roles()
            ->withoutGlobalScopes()
            ->get(['id', 'name', 'slug']);

        return inertia('Admin/Tenants/Show', [
            'tenant' => $tenant,
            'members' => $members,
            'roles' => $roles,
            'subscription' => $this->subscriptionData($tenant),
            'plans' => Plan::orderBy('sort')->get()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'key' => $plan->key,
                'name' => $plan->displayName(),
                'is_default' => $plan->is_default,
            ]),
            'overrides' => $tenant->featureOverrides()->get()->map(fn ($override) => [
                'feature' => $override->feature_key,
                'value' => $override->value,
                'expires_at' => $override->expires_at,
                'is_live' => $override->isLive(),
            ]),
            'entitlements' => $this->entitlementsPreview($tenant),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subscriptionData(Tenant $tenant): ?array
    {
        $subscription = $tenant->currentSubscription()?->load('plan');

        if (! $subscription) {
            return null;
        }

        return [
            'plan_id' => $subscription->plan_id,
            'plan_name' => $subscription->plan?->displayName(),
            'status' => $subscription->status->value,
            'trial_ends_at' => $subscription->trial_ends_at,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
        ];
    }

    /**
     * Read-only effective entitlements with their source (override/plan/default).
     *
     * @return array<int, array<string, mixed>>
     */
    private function entitlementsPreview(Tenant $tenant): array
    {
        $resolved = Entitlements::for($tenant);
        $plan = $tenant->activePlan();
        $planKeys = $plan ? $plan->planFeatures->pluck('feature_key')->all() : [];
        $overrideKeys = $tenant->liveFeatureOverrides()->pluck('feature_key')->all();

        return array_map(function (Feature $feature) use ($resolved, $planKeys, $overrideKeys) {
            $source = in_array($feature->value, $overrideKeys, true) ? 'override'
                : (in_array($feature->value, $planKeys, true) ? 'plan' : 'default');

            return [
                'key' => $feature->value,
                'label' => __($feature->labelKey()),
                'type' => $feature->type()->value,
                'group' => $feature->group(),
                'value' => $feature->isBoolean() ? $resolved->enabled($feature) : $resolved->limit($feature),
                'source' => $source,
            ];
        }, Feature::cases());
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant, UpdateTenantAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $oldSlug = $tenant->slug;
        $action->handle($tenant, $request->name, $request->slug);

        $this->logger->handle($request->user()->id, 'tenant.updated', $tenant, ['old_slug' => $oldSlug]);

        return back()->with('success', __('Tenant updated successfully.'));
    }

    public function destroy(Tenant $tenant, DeleteTenantAction $action): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        $tenantData = ['name' => $tenant->name, 'slug' => $tenant->slug, 'id' => $tenant->id];
        $action->handle($tenant);

        $this->logger->handle(request()->user()->id, 'tenant.deleted', null, $tenantData);

        return redirect()->route('admin.tenants.index')
            ->with('success', __('Tenant deleted successfully.'));
    }
}
