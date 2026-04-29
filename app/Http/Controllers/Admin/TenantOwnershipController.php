<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\TransferOwnershipAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantOwnershipController extends Controller
{
    public function update(Request $request, Tenant $tenant, TransferOwnershipAction $action): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $newOwner = User::findOrFail($request->user_id);
        $action->handle($tenant, $newOwner);

        return back()->with('success', __('Ownership transferred successfully.'));
    }
}
