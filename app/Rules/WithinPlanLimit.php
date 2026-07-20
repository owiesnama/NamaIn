<?php

namespace App\Rules;

use App\Features\Facades\Entitlements;
use App\Features\Feature;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when creating another record would exceed the tenant's plan limit for
 * the given feature. Reads the authoritative count from the entitlement layer,
 * independent of any client-supplied value.
 *
 * v1 note: this is an advisory check (two concurrent creates can both pass).
 * Hard limits (e.g. team seats) are additionally enforced in the domain action.
 */
class WithinPlanLimit implements ValidationRule
{
    public function __construct(
        private readonly Feature $feature,
        private readonly int $wanted = 1,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Entitlements::allows($this->feature, $this->wanted)) {
            $fail(__('entitlements.limit_reached', ['feature' => __($this->feature->labelKey())]));
        }
    }
}
