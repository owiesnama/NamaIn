<?php

namespace App\Http\Controllers\Users;

use App\Actions\Users\AcceptInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvitationController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = UserInvitation::withoutGlobalScopes()
            ->with('tenant', 'inviter', 'role')
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->isAccepted()) {
            return redirect()->route('login')->with('success', __('Invitation already accepted. Please log in.'));
        }

        if ($invitation->isExpired()) {
            return inertia('Invitations/Expired', ['tenant' => $invitation->tenant->name]);
        }

        return inertia('Invitations/Accept', [
            'token' => $token,
            'tenant' => $invitation->tenant->name,
            'inviter' => $invitation->inviter->name,
            'role' => $invitation->role->name,
            'email' => $invitation->email,
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token, AcceptInvitationAction $action): SymfonyResponse
    {
        $invitation = UserInvitation::withoutGlobalScopes()
            ->with('tenant', 'role')
            ->where('token', $token)
            ->firstOrFail();

        $user = $action->handle($invitation, $request->name, $request->password);

        auth()->login($user);
        $user->switchTenant($invitation->tenant);

        return Inertia::location(tenant_route('dashboard', $invitation->tenant->slug));
    }
}
