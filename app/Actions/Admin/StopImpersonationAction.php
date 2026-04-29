<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StopImpersonationAction
{
    public function __construct(private LogAdminAction $logger) {}

    public function handle(): User
    {
        $adminId = session('impersonating_from');

        if (! $adminId) {
            throw ValidationException::withMessages([
                'impersonation' => __('You are not currently impersonating anyone.'),
            ]);
        }

        $impersonatedUserId = session('impersonating_user_id');

        if (auth()->id() !== $impersonatedUserId) {
            throw ValidationException::withMessages([
                'impersonation' => __('Invalid impersonation session.'),
            ]);
        }

        if ($impersonatedUserId && session()->has('impersonating_previous_tenant_id')) {
            User::where('id', $impersonatedUserId)
                ->update(['current_tenant_id' => session('impersonating_previous_tenant_id')]);
        }

        $admin = User::findOrFail($adminId);

        $this->logger->handle($admin->id, 'impersonation.stopped', null, [
            'tenant_id' => session('impersonating_tenant_id'),
            'impersonated_user_id' => $impersonatedUserId,
        ]);

        session()->forget([
            'impersonating_from',
            'impersonating_user_id',
            'impersonating_tenant_id',
            'impersonating_tenant_name',
            'impersonating_started_at',
            'impersonating_previous_tenant_id',
            'impersonating_return_url',
        ]);

        Auth::guard('web')->login($admin);
        Auth::guard('admin')->login($admin);

        return $admin;
    }
}
