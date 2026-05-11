<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Active = 'active';
    case Converted = 'converted';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Converted => __('Converted'),
            self::Expired => __('Expired'),
        };
    }
}
