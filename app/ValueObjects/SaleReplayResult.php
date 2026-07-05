<?php

namespace App\ValueObjects;

use App\Models\Invoice;

/**
 * The outcome of replaying a pushed POS sale (Design 02 §5.4, §6): the recorded
 * invoice plus the server-derived flags surfaced back to the pushing device in
 * `results[].flags` (oversell lines and whether the sale breached credit).
 */
final readonly class SaleReplayResult
{
    /**
     * @param  list<array{product: string, oversold_qty: int}>  $oversell
     */
    public function __construct(
        public Invoice $invoice,
        public array $oversell,
        public bool $creditBreach,
    ) {}
}
