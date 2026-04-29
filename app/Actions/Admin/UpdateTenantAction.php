<?php

namespace App\Actions\Admin;

use App\Models\Tenant;

class UpdateTenantAction
{
    public function handle(Tenant $tenant, string $name, string $slug): Tenant
    {
        $tenant->update([
            'name' => $name,
            'slug' => strtolower($slug),
        ]);

        return $tenant;
    }
}
