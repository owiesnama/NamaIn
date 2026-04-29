<?php

namespace App\Actions\Admin;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteTenantAction
{
    public function handle(Tenant $tenant): void
    {
        if ($tenant->isActive()) {
            throw ValidationException::withMessages([
                'tenant' => __('Tenant must be deactivated before deletion.'),
            ]);
        }

        DB::transaction(function () use ($tenant) {
            $tenant->users()->each(function ($user) use ($tenant) {
                if ($user->current_tenant_id === $tenant->id) {
                    $user->update(['current_tenant_id' => null]);
                }
            });

            $tenant->users()->detach();
            $tenant->delete();
        });
    }
}
