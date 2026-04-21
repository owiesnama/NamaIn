<?php

namespace App\Actions\Fortify;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
            'tenant_name' => ['required', 'string', 'max:255'],
            'tenant_slug' => ['required', 'string', 'max:255', 'unique:tenants,slug', 'alpha_dash:ascii'],
        ])->validate();

        $tenant = Tenant::create([
            'name' => $input['tenant_name'],
            'slug' => Str::lower($input['tenant_slug']),
        ]);

        $user = User::firstOrCreate(
            ['email' => $input['email']],
            [
                'name' => $input['name'],
                'password' => Hash::make($input['password']),
            ]
        );

        $user->update(['current_tenant_id' => $tenant->id]);

        $tenant->users()->attach($user->id, ['role' => 'owner']);

        return $user;
    }
}
