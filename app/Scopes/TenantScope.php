<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = $this->resolveTenantId();

        if ($tenantId) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        } else {
            $builder->whereRaw('1 = 0');
        }
    }

    private function resolveTenantId(): int|string|null
    {
        if (app()->bound('currentTenant')) {
            return app('currentTenant')->id;
        }

        if (auth()->check()) {
            return auth()->user()->current_tenant_id;
        }

        return null;
    }
}
