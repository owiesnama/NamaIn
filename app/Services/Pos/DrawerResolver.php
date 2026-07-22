<?php

namespace App\Services\Pos;

use App\Enums\TreasuryAccountType;
use App\Models\Register;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves the cash drawer for a register (Design 01 §2.3, ratified
 * amendment): register-linked in sync/local contexts, sale-point-linked for
 * the reserved cloud register (R0) — so the cloud web path keeps resolving
 * exactly the drawer it always has.
 */
class DrawerResolver
{
    public function resolve(Register $register, Storage $salePoint): ?TreasuryAccount
    {
        return $this->query($register, $salePoint)->first();
    }

    public function resolveActive(Register $register, Storage $salePoint): ?TreasuryAccount
    {
        return $this->query($register, $salePoint)->active()->first();
    }

    /**
     * @return Builder<TreasuryAccount>
     */
    private function query(Register $register, Storage $salePoint): Builder
    {
        $query = TreasuryAccount::query()->ofType(TreasuryAccountType::Cash);

        return $register->isCloud()
            ? $query->where('sale_point_id', $salePoint->id)
            : $query->where('register_id', $register->id);
    }
}
