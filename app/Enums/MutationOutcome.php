<?php

namespace App\Enums;

/**
 * The per-mutation result status returned by POST /push (Design 02 §5.1).
 * `applied` and `already_applied` are both successes (the latter is an
 * idempotent replay — zero writes); `rejected` carries a {@see RejectionReason}.
 */
enum MutationOutcome: string
{
    case Applied = 'applied';
    case AlreadyApplied = 'already_applied';
    case Rejected = 'rejected';
}
