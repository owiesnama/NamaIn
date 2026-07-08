<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\LogAdminAction;
use App\Enums\AnnouncementAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAnnouncementRequest;
use App\Jobs\SendAnnouncementJob;
use App\Models\Announcement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class AnnouncementsController extends Controller
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

        return inertia('Admin/Announcements/Index', [
            'announcements' => Announcement::query()
                ->with('admin:id,name')
                ->latest()
                ->paginate(10, ['*'], 'announcements_page')
                ->withQueryString(),
            'tenants' => Tenant::query()->select(['id', 'name'])->orderBy('name')->get(),
            'users' => $users,
            'filters' => $request->only('search', 'verified', 'owners_only'),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $announcement = Announcement::create([
            'admin_user_id' => $request->user()->id,
            'subject' => $request->subject,
            'body' => $request->body,
            'audience_type' => $request->audience_type,
            'audience_meta' => $this->audienceMeta($request),
            'send_email' => $request->boolean('send_email'),
        ]);

        SendAnnouncementJob::dispatch($announcement);

        $this->logger->handle($request->user()->id, 'announcement.sent', $announcement, [
            'subject' => $announcement->subject,
            'audience_type' => $announcement->audience_type->value,
        ]);

        return back()->with('success', __('Your announcement has been queued for delivery.'));
    }

    public function tenantRoles(Tenant $tenant): JsonResponse
    {
        return response()->json(
            Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function audienceMeta(StoreAnnouncementRequest $request): ?array
    {
        return match (AnnouncementAudience::from($request->audience_type)) {
            AnnouncementAudience::Tenant => ['tenant_id' => (int) $request->tenant_id],
            AnnouncementAudience::TenantRole => [
                'tenant_id' => (int) $request->tenant_id,
                'role_id' => (int) $request->role_id,
            ],
            AnnouncementAudience::Users => ['user_ids' => $request->user_ids],
            default => null,
        };
    }
}
