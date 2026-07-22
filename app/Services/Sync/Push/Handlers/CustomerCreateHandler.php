<?php

namespace App\Services\Sync\Push\Handlers;

use App\Models\Customer;
use App\Models\Device;
use App\Models\User;
use App\Services\Sync\Push\MutationHandler;
use App\Services\Sync\Push\PushMutation;
use App\ValueObjects\Money;

/**
 * `customer.create` (Design 02 §5.3): a tenant-bound customer minted on the
 * device. `credit_limit` crosses as integer minor units and is stored through
 * the model's MoneyCast (major-unit input), so it is converted at the edge.
 */
class CustomerCreateHandler implements MutationHandler
{
    public function handle(PushMutation $mutation, User $actor, Device $device): array
    {
        $payload = $mutation->payload;

        $customer = Customer::create([
            'public_id' => $mutation->publicId,
            'name' => $payload['name'],
            'phone_number' => $payload['phone_number'] ?? null,
            'address' => $payload['address'] ?? null,
            'credit_limit' => isset($payload['credit_limit'])
                ? Money::fromMinor((int) $payload['credit_limit'])->major()
                : null,
        ]);

        return ['public_id' => $customer->public_id, 'serial' => null];
    }
}
