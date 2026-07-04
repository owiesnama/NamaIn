<?php

namespace App\Services\Sync;

/**
 * Server-side mirror of the `namain/sync-protocol` wire constants (Design 02
 * §9). The sibling package encodes the same values for the client; the cloud
 * does NOT depend on it (follow-up: swap these for the package constants when
 * the dependency wiring lands). Values here are the wire contract — change
 * them only with a protocol version bump.
 */
final class SyncProtocol
{
    public const BASE = '/api/sync/v1';

    public const VERSION = 1;

    /** Oldest protocol this server still speaks; older clients get 426. */
    public const MIN_PROTOCOL = 1;

    /** Advertised alongside upgrade_required so the client can name the update. */
    public const MIN_APP_VERSION = '1.0.0';

    public const HEADER_PROTOCOL = 'X-Sync-Protocol';

    public const HEADER_APP = 'X-App-Version';
}
