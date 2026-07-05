<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/SyncPushHelpers.php';

it('applies a customer.create mutation and returns applied', function () {
    $env = pushEnvironment();
    $publicId = strtolower((string) Str::ulid());

    $response = pushAs($env, [customerMutation($env, $publicId)]);

    $response->assertOk()
        ->assertJsonPath('protocol', 1)
        ->assertJsonPath('results.0.outcome', 'applied')
        ->assertJsonPath('results.0.public_id', $publicId)
        ->assertJsonPath('results.0.serial', null);

    $customer = Customer::where('public_id', $publicId)->first();
    expect($customer)->not->toBeNull();
    expect($customer->name)->toBe('Sara');
    expect($customer->tenant_id)->toBe($env['device']->tenant_id);
    // credit_limit stored in minor units, read back as major
    expect($customer->getRawOriginal('credit_limit'))->toBe(500000);
});

it('stamps source_device_id on the pushed change-log entry', function () {
    $env = pushEnvironment();
    $publicId = strtolower((string) Str::ulid());

    pushAs($env, [customerMutation($env, $publicId)])->assertOk();

    $entry = DB::table('change_log')
        ->where('table_name', 'customers')
        ->where('public_id', $publicId)
        ->first();

    expect((int) $entry->source_device_id)->toBe($env['device']->id);
    expect((int) $entry->actor_user_id)->toBe($env['actor']->id);
});

it('replays an already-applied mutation with zero new writes', function () {
    $env = pushEnvironment();
    $publicId = strtolower((string) Str::ulid());
    $mutation = customerMutation($env, $publicId);

    pushAs($env, [$mutation])->assertOk()->assertJsonPath('results.0.outcome', 'applied');

    $countBefore = Customer::count();

    $again = pushAs($env, [$mutation]);
    $again->assertOk()->assertJsonPath('results.0.outcome', 'already_applied')
        ->assertJsonPath('results.0.public_id', $publicId);

    expect(Customer::count())->toBe($countBefore);
});

it('resolves a within-batch forward reference across mutations', function () {
    $env = pushEnvironment();
    $sessionPublicId = strtolower((string) Str::ulid());

    $mutations = [
        [
            'idempotency_key' => (string) Str::ulid(),
            'type' => 'pos_session.open',
            'public_id' => $sessionPublicId,
            'actor' => $env['actor']->public_id,
            'occurred_at' => now()->toIso8601String(),
            'payload' => ['opening_float' => 100000],
        ],
        [
            'idempotency_key' => (string) Str::ulid(),
            'type' => 'pos_session.close',
            'public_id' => $sessionPublicId,
            'actor' => $env['actor']->public_id,
            'occurred_at' => now()->toIso8601String(),
            'payload' => ['session' => $sessionPublicId, 'closing_float' => 100000],
        ],
    ];

    $response = pushAs($env, $mutations);

    $response->assertOk()
        ->assertJsonPath('results.0.outcome', 'applied')
        ->assertJsonPath('results.1.outcome', 'applied');

    $session = PosSession::where('public_id', $sessionPublicId)->first();
    expect($session)->not->toBeNull();
    expect($session->isOpen())->toBeFalse();
});

it('rejects an unknown reference as retriable without persisting idempotency', function () {
    $env = pushEnvironment();
    $key = (string) Str::ulid();

    $mutation = [
        'idempotency_key' => $key,
        'type' => 'expense.create',
        'public_id' => strtolower((string) Str::ulid()),
        'actor' => $env['actor']->public_id,
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'title' => 'Ice',
            'amount' => 35000,
            'expensed_at' => '2026-07-03',
            'notes' => null,
            'categories' => [],
            'treasury_account' => strtolower((string) Str::ulid()), // unknown
            'receipt_public_id' => null,
        ],
    ];

    $response = pushAs($env, [$mutation]);

    $response->assertOk()
        ->assertJsonPath('results.0.outcome', 'rejected')
        ->assertJsonPath('results.0.reason', 'unknown_reference');

    expect(Expense::count())->toBe(0);
    // retriable rejections leave no idempotency row, so a re-push can succeed
    expect(DB::table('sync_idempotency')->where('idempotency_key', $key)->exists())->toBeFalse();
});

it('applies expense.create with resolved drawer and categories', function () {
    $env = pushEnvironment();
    $category = Category::create(['name' => 'Utilities', 'type' => 'expense']);
    $expensePublicId = strtolower((string) Str::ulid());

    $mutation = [
        'idempotency_key' => (string) Str::ulid(),
        'type' => 'expense.create',
        'public_id' => $expensePublicId,
        'actor' => $env['actor']->public_id,
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'title' => 'Ice for the fridge',
            'amount' => 35000,
            'expensed_at' => '2026-07-03',
            'notes' => null,
            'categories' => [$category->public_id],
            'treasury_account' => $env['drawer']->public_id,
            'receipt_public_id' => strtolower((string) Str::ulid()),
        ],
    ];

    pushAs($env, [$mutation])->assertOk()->assertJsonPath('results.0.outcome', 'applied');

    $expense = Expense::where('public_id', $expensePublicId)->first();
    expect($expense)->not->toBeNull();
    expect($expense->getRawOriginal('amount'))->toBe(35000);
    expect($expense->status->value)->toBe('pending');
    expect($expense->categories()->count())->toBe(1);
});

it('rejects a mutation whose actor is not in the tenant as tenant_mismatch', function () {
    $env = pushEnvironment();
    $stranger = User::factory()->create();

    $mutation = customerMutation($env, strtolower((string) Str::ulid()));
    $mutation['actor'] = $stranger->public_id;

    pushAs($env, [$mutation])->assertOk()
        ->assertJsonPath('results.0.outcome', 'rejected')
        ->assertJsonPath('results.0.reason', 'tenant_mismatch');
});

it('rejects a batch over the cap with 422', function () {
    $env = pushEnvironment();
    $mutations = [];
    for ($i = 0; $i < 201; $i++) {
        $mutations[] = customerMutation($env, strtolower((string) Str::ulid()));
    }

    pushAs($env, $mutations)->assertStatus(422);
});

it('requires the sync:push ability', function () {
    $env = pushEnvironment();
    // A token without sync:push cannot push.
    $limited = $env['device']->createToken('limited', ['sync:pull'])->plainTextToken;

    test()->postJson('/api/sync/v1/push', [
        'protocol' => 1,
        'mutations' => [customerMutation($env, strtolower((string) Str::ulid()))],
    ], ['Authorization' => "Bearer {$limited}"])->assertForbidden();
});
