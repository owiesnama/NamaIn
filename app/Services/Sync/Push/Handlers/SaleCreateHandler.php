<?php

namespace App\Services\Sync\Push\Handlers;

use App\Actions\Pos\ReplayPosSaleAction;
use App\Enums\StockPolicy;
use App\Exceptions\Sync\RejectedMutation;
use App\Models\Device;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Sync\PublicIdResolver;
use App\Services\Sync\Push\MutationHandler;
use App\Services\Sync\Push\PushMutation;
use App\Services\Sync\SyncMorphMap;
use App\ValueObjects\CheckoutContext;
use App\ValueObjects\Money;
use App\ValueObjects\PresetIdentity;
use Illuminate\Support\Collection;

/**
 * `sale.create` (Design 02 §5.4): resolves every public_id to a local id
 * (unknown refs reject retriably), rebuilds the checkout payload in the
 * major-unit shape the shared action consumes, and replays it on the device's
 * register with the device-minted preset identity so the serial and row ids are
 * stored verbatim. Oversell and credit-breach flags come back in the result.
 */
class SaleCreateHandler implements MutationHandler
{
    public function __construct(
        private ReplayPosSaleAction $replaySale,
        private PublicIdResolver $resolver,
    ) {}

    public function handle(PushMutation $mutation, User $actor, Device $device): array
    {
        $payload = $mutation->payload;

        $session = $this->resolveSession($payload['session'] ?? null);
        $data = $this->buildCheckoutData($payload);
        $context = new CheckoutContext(
            register: $device->register,
            stockPolicy: StockPolicy::AllowNegative,
            executeReplenishment: false,
            preset: $this->presetIdentity($mutation, $payload),
        );

        $result = $this->replaySale->handle($session, $data, $actor, $context, $device);

        return [
            'public_id' => $result->invoice->public_id,
            'serial' => $result->invoice->serial_number,
            'flags' => [
                'oversell' => $result->oversell,
                'credit_breach' => $result->creditBreach,
            ],
        ];
    }

    private function resolveSession(?string $publicId): PosSession
    {
        $sessionId = $this->resolver->id(PosSession::class, $publicId);

        if ($sessionId === null) {
            throw RejectedMutation::unknownReference(__('The POS session for this sale has not synced yet.'));
        }

        return PosSession::findOrFail($sessionId);
    }

    /**
     * Rebuild the checkout collection the way the web request shapes it: money in
     * major units (the model's MoneyCast re-encodes it), references as int ids.
     *
     * @param  array<string, mixed>  $payload
     * @return Collection<string, mixed>
     */
    private function buildCheckoutData(array $payload): Collection
    {
        return collect([
            'customer_id' => $this->resolveCustomer($payload),
            'customer_type' => SyncMorphMap::modelClass($payload['customer_type'] ?? 'customer'),
            'payment_method' => $payload['payment_method'] ?? 'cash',
            'total' => Money::fromMinor((int) $payload['total'])->major(),
            'discount' => Money::fromMinor((int) ($payload['discount'] ?? 0))->major(),
            'items' => array_map(fn (array $item): array => $this->resolveItem($item), $payload['items']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCustomer(array $payload): ?int
    {
        $customerPublicId = $payload['customer'] ?? null;

        if ($customerPublicId === null) {
            return null; // walk-in; the action first-or-creates the system customer
        }

        $modelClass = SyncMorphMap::modelClass($payload['customer_type'] ?? 'customer');
        $customerId = $this->resolver->id($modelClass, $customerPublicId);

        if ($customerId === null) {
            throw RejectedMutation::unknownReference(__('The customer on this sale has not synced yet.'));
        }

        return $customerId;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{product_id: int, unit_id: ?int, quantity: mixed, price: float}
     */
    private function resolveItem(array $item): array
    {
        $productId = $this->resolver->id(Product::class, $item['product'] ?? null);

        if ($productId === null) {
            throw RejectedMutation::unknownReference(__('A product on this sale has not synced yet.'));
        }

        $unitId = $this->resolver->id(Unit::class, $item['unit'] ?? null);

        if (isset($item['unit']) && $unitId === null) {
            throw RejectedMutation::unknownReference(__('A unit on this sale has not synced yet.'));
        }

        return [
            'product_id' => $productId,
            'unit_id' => $unitId,
            // The wire carries decimal-formatted strings ("2.0000"); the local
            // column is integer. SQLite coerces silently but MySQL strict mode
            // rejects the raw string — field-caught as the production 500 that
            // froze the pilot device's queue. Normalize at the boundary.
            'quantity' => (int) round((float) $item['quantity']),
            'price' => Money::fromMinor((int) $item['price'])->major(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function presetIdentity(PushMutation $mutation, array $payload): PresetIdentity
    {
        return new PresetIdentity(
            serialNumber: $payload['serial_number'],
            invoicePublicId: $mutation->publicId,
            linePublicIds: array_map(fn (array $item): string => $item['public_id'], $payload['items']),
            paymentPublicId: $payload['payment']['public_id'] ?? null,
        );
    }
}
