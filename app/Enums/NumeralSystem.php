<?php

namespace App\Enums;

enum NumeralSystem: string
{
    case Arabic = 'arabic';
    case Latin = 'latin';

    /**
     * Human-readable label (localize at the call site via __()).
     */
    public function label(): string
    {
        return match ($this) {
            self::Arabic => 'Arabic-Indic',
            self::Latin => 'Latin',
        };
    }

    /**
     * The Intl locale that renders this numeral system.
     *
     * Arabic-Indic output carries its own bidi controls (U+061C, U+200F) placed
     * by ICU for an RTL context; Latin output does not and must be isolated by
     * the caller. See resources/js/Components/Money.vue.
     */
    public function intlLocale(): string
    {
        return match ($this) {
            self::Arabic => 'ar-SA',
            self::Latin => 'en-US',
        };
    }

    /**
     * The system a tenant gets when it has never chosen one.
     */
    public static function defaultForLocale(string $locale): self
    {
        return str_starts_with($locale, 'ar') ? self::Arabic : self::Latin;
    }
}
