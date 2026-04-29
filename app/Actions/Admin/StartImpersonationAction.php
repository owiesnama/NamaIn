<?php

namespace App\Actions\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StartImpersonationAction
{
    public function __construct(private LogAdminAction $logger) {}

    public function handle(User $admin, Tenant $tenant, User $targetUser): User
    {
        if (session()->has('impersonating_from')) {
            throw ValidationException::withMessages([
                'impersonation' => __('You are already impersonating a tenant. Stop the current session first.'),
            ]);
        }

        if (! $targetUser->belongsToTenant($tenant)) {
            throw ValidationException::withMessages([
                'user' => __('This user is not a member of this tenant.'),
            ]);
        }

        $previousTenantId = $targetUser->current_tenant_id;

        session()->put([
            'impersonating_from' => $admin->id,
            'impersonating_user_id' => $targetUser->id,
            'impersonating_tenant_id' => $tenant->id,
            'impersonating_tenant_name' => $tenant->name,
            'impersonating_started_at' => now()->toIso8601String(),
            'impersonating_previous_tenant_id' => $previousTenantId,
            'impersonating_return_url' => route('admin.tenants.show', $tenant),
        ]);

        $this->logger->handle($admin->id, 'impersonation.started', $tenant, [
            'target_user_id' => $targetUser->id,
            'target_user_email' => $targetUser->email,
        ]);

        $targetUser->update(['current_tenant_id' => $tenant->id]);

        Auth::guard('web')->login($targetUser);

        return $targetUser;
    }
}
