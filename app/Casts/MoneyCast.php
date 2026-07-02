<?php

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores money in minor units while the application layer reads and
 * writes major units.
 */
class MoneyCast implements CastsAttributes
{
    /**
     * Cast the stored minor units to major units.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        if ($value === null) {
            return null;
        }

        return Money::fromMinor((int) $value)->major();
    }

    /**
     * Prepare the given major-unit value for storage in minor units.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        return Money::fromMajor($value)->minor();
    }
}
