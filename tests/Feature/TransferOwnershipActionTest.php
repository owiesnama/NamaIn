<?php

use App\Actions\Admin\TransferOwnershipAction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = app('currentTenant');
    seedTenantRoles($this->tenant);

    $this->ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $this->managerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'manager')->first();

    $this->currentOwner = User::factory()->create();
    $this->tenant->users()->attach($this->currentOwner, ['role' => 'owner', 'role_id' => $this->ownerRole->id, 'is_active' => true]);

    $this->member = User::factory()->create();
    $this->tenant->users()->attach($this->member, ['role' => 'manager', 'role_id' => $this->managerRole->id, 'is_active' => true]);
});

test('it promotes the new owner and demotes the previous owner to manager', function () {
    app(TransferOwnershipAction::class)->handle($this->tenant, $this->member);

    $pivotFor = fn (User $user) => $this->tenant->users()->where('users.id', $user->id)->first()->pivot;

    expect($pivotFor($this->member)->role)->toBe('owner')
        ->and((int) $pivotFor($this->member)->role_id)->toBe($this->ownerRole->id)
        ->and($pivotFor($this->currentOwner)->role)->toBe('manager')
        ->and((int) $pivotFor($this->currentOwner)->role_id)->toBe($this->managerRole->id);
});

test('it rejects users who are not members of the tenant', function () {
    $outsider = User::factory()->create();

    expect(fn () => app(TransferOwnershipAction::class)->handle($this->tenant, $outsider))
        ->toThrow(ValidationException::class);

    $ownerPivot = $this->tenant->users()->where('users.id', $this->currentOwner->id)->first()->pivot;
    expect($ownerPivot->role)->toBe('owner');
});
