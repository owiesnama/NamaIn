<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--name= : The admin name}
                            {--email= : The admin email}
                            {--password= : The admin password}';

    protected $description = 'Create a new super-admin user or promote an existing one';

    public function handle(): int
    {
        $email = $this->option('email') ?? $this->ask('Email');

        $existing = User::where('email', $email)->first();

        if ($existing) {
            return $this->promoteExisting($existing);
        }

        return $this->createNew($email);
    }

    private function promoteExisting(User $user): int
    {
        if ($user->isAdmin()) {
            $this->warn("{$user->email} is already an admin.");

            return self::SUCCESS;
        }

        $user->update(['role' => 'admin']);
        $this->info("Promoted {$user->email} to admin.");

        return self::SUCCESS;
    }

    private function createNew(string $email): int
    {
        $name = $this->option('name') ?? $this->ask('Name');
        $password = $this->option('password') ?? $this->secret('Password');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->info("Admin user created: {$email}");

        return self::SUCCESS;
    }
}
