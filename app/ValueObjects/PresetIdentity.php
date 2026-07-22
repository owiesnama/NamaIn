<?php

namespace App\ValueObjects;

/**
 * Pre-minted identity for a replayed POS sale (Design 03 §5.2): the device
 * minted the serial and public_ids inside its own checkout transaction, so a
 * replay must reproduce identical rows instead of minting new identities.
 */
final readonly class PresetIdentity
{
    /**
     * @param  array<int, string>  $linePublicIds  invoice-line public_ids, keyed by item index
     */
    public function __construct(
        public string $serialNumber,
        public string $invoicePublicId,
        public array $linePublicIds = [],
        public ?string $paymentPublicId = null,
    ) {}
}
