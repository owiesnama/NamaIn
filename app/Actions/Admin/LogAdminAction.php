<?php

namespace App\Actions\Admin;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;

class LogAdminAction
{
    public function handle(
        int $adminUserId,
        string $action,
        ?Model $target = null,
        ?array $metadata = null,
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'target_type' => $target ? $target->getMorphClass() : null,
            'target_id' => $target?->getKey(),
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
