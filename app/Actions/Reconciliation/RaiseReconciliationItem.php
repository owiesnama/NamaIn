<?php

namespace App\Actions\Reconciliation;

use App\Enums\ReconciliationType;
use App\Models\Device;
use App\Models\ReconciliationItem;
use App\Models\Register;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Raises the inbox row for a divergence (Design 04 §1.3, R2). Called by the push
 * pipeline *inside the per-mutation transaction*, right after the concrete
 * subject is created, so the inbox row commits atomically with its subject and
 * can never be missed nor double-counted on replay. `detected_at` is server
 * time; `occurred_at` is the device business time carried on the subject.
 */
class RaiseReconciliationItem
{
    /**
     * @param  Model  $subject  One of the four concrete subject models.
     */
    public function for(
        Model $subject,
        ReconciliationType $type,
        ?Device $device = null,
        ?Register $register = null,
        ?User $actor = null,
        CarbonInterface|string|null $occurredAt = null,
    ): ReconciliationItem {
        return ReconciliationItem::create([
            'tenant_id' => $subject->tenant_id ?? $device?->tenant_id,
            'type' => $type,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'device_id' => $device?->id,
            'register_id' => $register?->id,
            'actor_user_id' => $actor?->id,
            'occurred_at' => $occurredAt ?? now(),
            'detected_at' => now(),
            'status' => ReconciliationItem::STATUS_OPEN,
        ]);
    }
}
