<?php

namespace App\Enums;

/**
 * The derived fleet-health state of a device (Design 04 §4.1), computed from
 * `devices` columns rather than stored. Shown as a status pill on the fleet
 * dashboard; `skewed` and `offline` also ride the daily digest.
 */
enum DeviceHealth: string
{
    case Revoked = 'revoked';
    case Skewed = 'skewed';
    case Offline = 'offline';
    case Stale = 'stale';
    case Healthy = 'healthy';

    public function label(): string
    {
        return match ($this) {
            self::Revoked => __('Revoked'),
            self::Skewed => __('Clock skew'),
            self::Offline => __('Offline'),
            self::Stale => __('Outbox stuck'),
            self::Healthy => __('Healthy'),
        };
    }
}
