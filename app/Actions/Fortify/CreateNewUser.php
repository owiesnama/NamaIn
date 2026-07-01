<?php

namespace App\Actions\Fortify;

use App\Actions\ProvisionTenantAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private ProvisionTenantAction $provisionTenant) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'tenant_name' => ['required', 'string', 'max:255'],
            'tenant_slug' => ['required', 'string', 'max:255', 'unique:tenants,slug', 'alpha_dash:ascii'],
        ])->validate();

        [$user, $tenant] = DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            $tenant = $this->provisionTenant->handle($input['tenant_name'], $input['tenant_slug'], $user);

            return [$user, $tenant];
        });

        if (app()->isLocal()) {
            Process::run('herd link '.$tenant->slug.'.namain.test');
        }

        return $user;
    }
}
