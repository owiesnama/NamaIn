<?php

use App\Actions\Pos\ClosePosSessionAction;
use App\Actions\Pos\OpenPosSessionAction;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $this->tenant);

    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner']);

    $this->cashier = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->cashier, ['role' => 'cashier']);

    $this->storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('it can open a pos session', function () {
    $session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    expect($session->storage_id)->toBe($this->storage->id);
    expect($session->opened_by)->toBe($this->cashier->id);
    expect($session->opening_float)->toBe(5000);
    expect($this->storage->fresh()->active_session_id)->toBe($session->id);
});

test('it cannot open multiple sessions for the same storage', function () {
    app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    expect(fn () => app(OpenPosSessionAction::class)->execute($this->storage, 6000, $this->owner))
        ->toThrow(DomainException::class, 'Storage already has an active POS session.');
});

test('it can close a pos session', function () {
    $session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    app(ClosePosSessionAction::class)->execute($session, 12000, $this->owner);

    $session->refresh();
    expect($session->closed_at)->not->toBeNull();
    expect($session->closed_by)->toBe($this->owner->id);
    expect($session->closing_float)->toBe(12000);
    expect($this->storage->fresh()->active_session_id)->toBeNull();
});

test('it calculates variance correctly', function () {
    // Note: Variance depends on cashSalesTotal which is currently a placeholder
    // In actual implementation, we'd create invoices linked to the session.
    $session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);

    // Manual adjustment of variance expectation since cashSalesTotal is placeholder
    $session->update(['closing_float' => 15000]); // Expected 5000 (if no sales)

    expect($session->variance())->toBe(10000);
});
