<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;

/**
 * Tenant management controller — not yet implemented.
 * Each method returns 501 until feature work begins.
 */
class TenantsController extends Controller
{
    public function index()
    {
        abort(501, 'Not implemented.');
    }

    public function create()
    {
        abort(501, 'Not implemented.');
    }

    public function store(StoreTenantRequest $request)
    {
        abort(501, 'Not implemented.');
    }

    public function show(Tenant $tenant)
    {
        abort(501, 'Not implemented.');
    }

    public function edit(Tenant $tenant)
    {
        abort(501, 'Not implemented.');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant)
    {
        abort(501, 'Not implemented.');
    }

    public function destroy(Tenant $tenant)
    {
        abort(501, 'Not implemented.');
    }
}
