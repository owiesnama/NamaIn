<?php

namespace App\Actions\Admin;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ResolveAnnouncementAudienceAction
{
    /**
     * @return Builder<User>
     */
    public function handle(Announcement $announcement): Builder
    {
        return match ($announcement->audience_type) {
            AnnouncementAudience::All => User::query(),
            AnnouncementAudience::Tenant => User::whereHas('tenants', fn ($q) => $q
                ->where('tenants.id', $announcement->audience_meta['tenant_id'])),
            AnnouncementAudience::Owners => User::whereHas('tenants', fn ($q) => $q
                ->where('tenant_user.role', 'owner')),
            AnnouncementAudience::TenantRole => User::whereHas('tenants', fn ($q) => $q
                ->where('tenants.id', $announcement->audience_meta['tenant_id'])
                ->where('tenant_user.role_id', $announcement->audience_meta['role_id'])),
            AnnouncementAudience::Users => User::whereIn('id', $announcement->audience_meta['user_ids']),
        };
    }
}
