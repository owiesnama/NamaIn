<?php

namespace App\Actions\Pos;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\TreasuryMovementReason;
use App\Models\PosSession;
use App\Models\Register;
use App\Models\Storage;
use App\Models\User;
use App\Services\Pos\DrawerResolver;
use Illuminate\Support\Facades\DB;

class OpenPosSessionAction
{
    public function __construct(
        private RecordTreasuryMovementAction $recordMovement,
        private DrawerResolver $drawerResolver,
    ) {}

    /**
     * Open a POS session on a sale point. The register decides which cash
     * drawer receives the opening float (Design 01 §2.3): the cloud web
     * default (R0) keeps the sale-point drawer; sync/local callers pass
     * their device register to hit the register-linked drawer.
     */
    public function handle(Storage $storage, int $openingFloat, User $actor, ?Register $register = null): PosSession
    {
        return DB::transaction(function () use ($storage, $openingFloat, $actor, $register) {
            // Lock storage row to prevent race condition on opening multiple sessions
            $storage = Storage::where('id', $storage->id)->lockForUpdate()->first();

            if ($storage->active_session_id) {
                throw new \DomainException('Storage already has an active POS session.');
            }

            $session = PosSession::create([
                'tenant_id' => $storage->tenant_id,
                'storage_id' => $storage->id,
                'opened_by' => $actor->id,
                'opening_float' => $openingFloat,
            ]);

            $storage->update([
                'active_session_id' => $session->id,
            ]);

            $register ??= Register::cloudRegisterFor($storage->tenant_id);
            $cashDrawer = $this->drawerResolver->resolve($register, $storage);

            if ($cashDrawer && $openingFloat > 0) {
                $this->recordMovement->handle(
                    account: $cashDrawer,
                    amount: $openingFloat,
                    reason: TreasuryMovementReason::PosOpeningFloat,
                    movable: $session,
                    actor: $actor,
                );
            }

            return $session;
        });
    }
}
