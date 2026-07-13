<?php

namespace App\Enums;

enum ProductType: string
{
    case Physical = 'physical';
    case Service = 'service';

    /**
     * Human-readable label (localize at the call site via __()).
     */
    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Physical',
            self::Service => 'Service',
        };
    }
}
