<?php

use App\Enums\ReconciliationType;
use App\Enums\RejectionReason;
use App\Enums\ResolutionKind;
use App\Models\CreditBreachFlag;
use App\Models\OversellReconciliation;
use App\Models\ParkedMutation;
use App\Models\ReconciliationItem;
use App\Models\SessionVariance;
use Carbon\CarbonInterface;

it('casts reconciliation item enums, times and status helpers', function () {
    $item = ReconciliationItem::factory()->create([
        'type' => ReconciliationType::Oversell,
        'resolution' => ResolutionKind::Adjust,
        'status' => ReconciliationItem::STATUS_RESOLVED,
    ]);

    expect($item->type)->toBe(ReconciliationType::Oversell);
    expect($item->resolution)->toBe(ResolutionKind::Adjust);
    expect($item->occurred_at)->toBeInstanceOf(CarbonInterface::class);
    expect($item->detected_at)->toBeInstanceOf(CarbonInterface::class);
    expect($item->isOpen())->toBeFalse();
    expect($item->public_id)->not->toBeNull();
});

it('scopes open items only', function () {
    ReconciliationItem::factory()->create();
    ReconciliationItem::factory()->resolved()->create();

    expect(ReconciliationItem::open()->count())->toBe(1);
});

it('resolves the polymorphic subject relation', function () {
    $item = ReconciliationItem::factory()->create();

    expect($item->subject)->toBeInstanceOf(ParkedMutation::class);
});

it('casts parked mutation envelope and rejection reason', function () {
    $parked = ParkedMutation::factory()->create([
        'rejection_reason' => RejectionReason::SessionClosed,
        'envelope' => ['type' => 'pos_session.close', 'payload' => ['x' => 1]],
    ]);

    expect($parked->rejection_reason)->toBe(RejectionReason::SessionClosed);
    expect($parked->envelope)->toBe(['type' => 'pos_session.close', 'payload' => ['x' => 1]]);
    expect($parked->public_id)->not->toBeNull();
});

it('casts session variance amounts as integer minor units', function () {
    $variance = SessionVariance::factory()->create([
        'expected_amount' => 100000,
        'declared_amount' => 99000,
        'variance_amount' => -1000,
    ]);

    expect($variance->expected_amount)->toBe(100000);
    expect($variance->variance_amount)->toBe(-1000);
    expect($variance->session)->not->toBeNull();
    expect($variance->drawer)->not->toBeNull();
    expect($variance->register)->not->toBeNull();
});

it('maps each reconciliation type to its subject class and resolutions', function () {
    expect(ReconciliationType::Oversell->subjectClass())->toBe(OversellReconciliation::class);
    expect(ReconciliationType::CreditBreach->subjectClass())->toBe(CreditBreachFlag::class);
    expect(ReconciliationType::SessionVariance->subjectClass())->toBe(SessionVariance::class);
    expect(ReconciliationType::ParkedMutation->subjectClass())->toBe(ParkedMutation::class);

    expect(ReconciliationType::Oversell->resolutions())->toContain(ResolutionKind::Adjust);
    expect(ReconciliationType::CreditBreach->resolutions())->toContain(ResolutionKind::Collect);
});
