<?php

namespace App\ValueObjects;

use App\Enums\StockPolicy;
use App\Models\Register;
use App\Models\Tenant;

/**
 * The caller-specific half of a POS checkout (Design 03 §5.2): web, push
 * replay, and the local runtime are three contexts of one action.
 *
 * | Caller        | register | stockPolicy   | replenishment | preset |
 * |---------------|----------|---------------|---------------|--------|
 * | Cloud web POS | R0       | Strict        | yes           | —      |
 * | Push replay   | device's | AllowNegative | no            | serial + public_ids |
 * | Local runtime | device's | AllowNegative | no            | — (mints locally) |
 */
final readonly class CheckoutContext
{
    public function __construct(
        public Register $register,
        public StockPolicy $stockPolicy,
        public bool $executeReplenishment,
        public ?PresetIdentity $preset = null,
    ) {}

    /**
     * The cloud web POS context: strict stock on the tenant's reserved R0
     * register with the auto-replenishment transfer path enabled.
     */
    public static function cloudWeb(Tenant|int $tenant): self
    {
        return new self(
            register: Register::cloudRegisterFor($tenant),
            stockPolicy: StockPolicy::Strict,
            executeReplenishment: true,
        );
    }

    public function allowsNegativeStock(): bool
    {
        return $this->stockPolicy === StockPolicy::AllowNegative;
    }
}
