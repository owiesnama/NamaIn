<?php

namespace App\Actions\Pos;

use App\Actions\Reconciliation\RaiseReconciliationItem;
use App\Enums\ReconciliationType;
use App\Enums\TreasuryMovementReason;
use App\Models\Device;
use App\Models\PosSession;
use App\Models\Register;
use App\Models\SessionVariance;
use App\Models\TreasuryMovement;
use App\Models\User;
use App\Services\Pos\DrawerResolver;
use Carbon\CarbonInterface;

/**
 * Replays a pushed `pos_session.close` (Design 04 §2.3). A thin wrapper over
 * {@see ClosePosSessionAction}: it resolves the drawer by `register_id` (not
 * `sale_point_id` — §0.2/§8), captures the expected balance *before* the
 * reconciliation adjustment, runs the close (which absorbs `declared - expected`
 * into a drawer adjustment exactly as the cloud path does), and — for an
 * offline-originated close whose declared count disagreed — records a
 * `session_variance` and raises a reconciliation item. The cash flow is
 * unchanged from cloud closes; offline closes additionally *surface* the
 * variance to a human.
 */
class ReplayCloseSessionAction
{
    public function __construct(
        private ClosePosSessionAction $closeSession,
        private DrawerResolver $drawerResolver,
        private RaiseReconciliationItem $raiseReconciliationItem,
    ) {}

    public function handle(
        PosSession $session,
        int $closingFloat,
        User $actor,
        Register $register,
        ?Device $device = null,
        CarbonInterface|string|null $occurredAt = null,
    ): void {
        $drawer = $this->drawerResolver->resolve($register, $session->storage);
        $expected = $drawer?->currentBalance() ?? $closingFloat;

        $this->closeSession->handle($session, $closingFloat, $actor, $register);

        if ($device === null || $drawer === null || $closingFloat === $expected) {
            return;
        }

        $variance = SessionVariance::create([
            'tenant_id' => $session->tenant_id,
            'device_id' => $device->id,
            'register_id' => $register->id,
            'pos_session_id' => $session->id,
            'treasury_account_id' => $drawer->id,
            'expected_amount' => $expected,
            'declared_amount' => $closingFloat,
            'variance_amount' => $closingFloat - $expected,
            'adjustment_movement_id' => $this->latestAdjustmentId($drawer->id),
            'occurred_at' => $occurredAt ?? now(),
        ]);

        $this->raiseReconciliationItem->for(
            subject: $variance,
            type: ReconciliationType::SessionVariance,
            device: $device,
            register: $register,
            actor: $actor,
            occurredAt: $variance->occurred_at,
        );
    }

    /**
     * The reconciliation adjustment {@see ClosePosSessionAction} just recorded on
     * the drawer (the newest manual adjustment on that account).
     */
    private function latestAdjustmentId(int $drawerId): ?int
    {
        return TreasuryMovement::where('treasury_account_id', $drawerId)
            ->where('reason', TreasuryMovementReason::ManualAdjustment)
            ->latest('id')
            ->value('id');
    }
}
