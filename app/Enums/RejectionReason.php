<?php

namespace App\Enums;

/**
 * Why a push mutation was rejected (Design 02 §5.1, §8.5). Server-side mirror
 * of the `namain/sync-protocol` `RejectionReason` enum. `isRetriable()` is the
 * single source of retriability shared with the client (Design 03 D5): only an
 * unresolved reference is worth re-pushing — the missing parent may land next.
 */
enum RejectionReason: string
{
    case UnknownReference = 'unknown_reference';
    case ValidationFailed = 'validation_failed';
    case SessionClosed = 'session_closed';
    case UpgradeRequired = 'upgrade_required';
    case TenantMismatch = 'tenant_mismatch';

    public function isRetriable(): bool
    {
        return $this === self::UnknownReference;
    }

    /**
     * The human, localized message surfaced to the pushing device. Keys follow
     * the `sync.rejection.{reason}` convention (Design 02 §8.5) but resolve
     * through the app's JSON catalogs, so the framework-free package never owns
     * localization.
     */
    public function message(): string
    {
        return match ($this) {
            self::UnknownReference => __('A referenced record has not synced yet.'),
            self::ValidationFailed => __('The mutation payload failed validation.'),
            self::SessionClosed => __('The POS session is already closed.'),
            self::UpgradeRequired => __('This app version is no longer supported.'),
            self::TenantMismatch => __('The mutation actor does not belong to this organization.'),
        };
    }
}
