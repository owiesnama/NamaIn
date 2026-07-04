<?php

namespace App\Actions\Pos;

use App\Actions\Treasury\RecordTreasuryAdjustmentAction;
use App\Models\PosSession;
use App\Models\Register;
use App\Models\User;
use App\Services\Pos\DrawerResolver;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

class ClosePosSessionAction
{
    public function __construct(
        private RecordTreasuryAdjustmentAction $recordAdjustment,
        private DrawerResolver $drawerResolver,
    ) {}

    /**
     * Close a POS session and reconcile its cash drawer. The register decides
     * which drawer is reconciled (Design 01 §2.3): the cloud web default (R0)
     * keeps the sale-point drawer; sync/local callers pass their device
     * register to reconcile the register-linked drawer.
     */
    public function handle(PosSession $session, int $closingFloat, User $actor, ?Register $register = null): void
    {
        if (! $session->isOpen()) {
            throw new \DomainException('POS session is already closed.');
        }

        DB::transaction(function () use ($session, $closingFloat, $actor, $register) {
            $session->update([
                'closed_by' => $actor->id,
                'closing_float' => $closingFloat,
                'closed_at' => now(),
            ]);

            $session->storage->update([
                'active_session_id' => null,
            ]);

            $register ??= Register::cloudRegisterFor($session->tenant_id);
            $cashDrawer = $this->drawerResolver->resolve($register, $session->storage);

            if ($cashDrawer) {
                $expected = $cashDrawer->currentBalance();

                if ($expected !== $closingFloat) {
                    $this->recordAdjustment->handle(
                        account: $cashDrawer,
                        newBalance: $closingFloat,
                        notes: __('POS session #:id closing reconciliation. Expected: :expected, Counted: :counted', [
                            'id' => $session->id,
                            'expected' => number_format(Money::fromMinor($expected)->major(), 2),
                            'counted' => number_format(Money::fromMinor($closingFloat)->major(), 2),
                        ]),
                        actor: $actor,
                    );
                }
            }
        });
    }
}
