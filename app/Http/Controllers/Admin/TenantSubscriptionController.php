<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\LogAdminAction;
use App\Actions\Subscriptions\AssignPlanToTenant;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TenantSubscriptionController extends Controller
{
    public function __construct(private readonly LogAdminAction $logger) {}

    public function update(Request $request, Tenant $tenant, AssignPlanToTenant $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'trial_ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $trialEndsAt = empty($data['trial_ends_at']) ? null : Carbon::parse($data['trial_ends_at']);

        $action->handle($tenant, $plan, $trialEndsAt);

        $this->logger->handle($request->user()->id, 'tenant.plan_assigned', $tenant, [
            'plan_id' => $plan->id,
            'plan_key' => $plan->key,
            'trial' => (bool) $trialEndsAt,
        ]);

        return back()->with('success', __('Plan assigned successfully.'));
    }
}
