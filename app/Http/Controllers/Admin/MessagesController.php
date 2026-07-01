<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\LogAdminAction;
use App\Actions\Admin\SendAdminEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendAdminEmailRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class MessagesController extends Controller
{
    public function __construct(private LogAdminAction $logger) {}

    public function index(Request $request): Response
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'email_verified_at'])
            ->when($request->search, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($request->verified === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($request->verified === 'verified', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($request->boolean('owners_only'), fn ($q) => $q->whereHas('tenants', fn ($q) => $q->where('tenant_user.role', 'owner')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return inertia('Admin/Messages/Index', [
            'users' => $users,
            'filters' => $request->only('search', 'verified', 'owners_only'),
        ]);
    }

    public function store(SendAdminEmailRequest $request, SendAdminEmailAction $action): RedirectResponse
    {
        $count = $action->handle(
            $request->user_ids,
            $request->subject,
            $request->body,
            $request->from_name,
            $request->reply_to,
        );

        $this->logger->handle($request->user()->id, 'message.sent', null, [
            'recipient_count' => $count,
            'subject' => $request->subject,
        ]);

        return back()->with('success', __('Your email has been queued for :count recipient(s).', ['count' => $count]));
    }
}
