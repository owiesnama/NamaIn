<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Features\Facades\Entitlements;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Assigns a plan to a tenant, terminating any prior live subscription in the
 * same transaction so at most one subscription is ever live per tenant.
 */
class AssignPlanToTenant
{
    public function handle(Tenant $tenant, Plan $plan, ?DateTimeInterface $trialEndsAt = null): Subscription
    {
        $subscription = DB::transaction(function () use ($tenant, $plan, $trialEndsAt): Subscription {
            $tenant->subscriptions()
                ->whereIn('status', array_map(fn ($s) => $s->value, SubscriptionStatus::live()))
                ->update([
                    'status' => SubscriptionStatus::Canceled,
                    'ends_at' => now(),
                ]);

            return $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => $trialEndsAt ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'trial_ends_at' => $trialEndsAt,
                'starts_at' => now(),
                'ends_at' => null,
            ]);
        });

        Entitlements::flush($tenant);

        return $subscription;
    }
}
