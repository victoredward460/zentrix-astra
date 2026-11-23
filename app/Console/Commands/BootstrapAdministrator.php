<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BootstrapAdministrator extends Command
{
    protected $signature = 'zentrix:bootstrap-admin';

    protected $description = 'Interactively create the first ZENTRIX ASTRA administrator';

    public function handle(): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            $this->error('The required user schema is missing. Run the migrations before bootstrapping an administrator.');

            return self::FAILURE;
        }

        if ($this->administratorExists()) {
            $this->error('An administrator already exists. This bootstrap command can only create the first one.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Administrator name'));
        $email = Str::lower(trim((string) $this->ask('Administrator email')));

        $identity = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            ],
        );

        if ($identity->fails()) {
            foreach ($identity->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = $this->secret('Administrator password (hidden input)');
        $confirmation = $this->secret('Confirm administrator password');

        $passwordValidation = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', PasswordRules::strong()]],
        );

        if ($passwordValidation->fails() || ! hash_equals((string) $password, (string) $confirmation)) {
            foreach ($passwordValidation->errors()->all() as $error) {
                $this->error($error);
            }

            if (! hash_equals((string) $password, (string) $confirmation)) {
                $this->error('The passwords did not match.');
            }

            return self::FAILURE;
        }

        if (! $this->confirm('Create the first administrator now?', false)) {
            $this->comment('Administrator bootstrap cancelled.');

            return self::SUCCESS;
        }

        $created = DB::transaction(function () use ($name, $email, $password): bool {
            if ($this->administratorExists()) {
                return false;
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->forceFill(['role' => UserRole::Administrator])->save();

            return true;
        });

        if (! $created) {
            $this->error('An administrator was created by another process. No account was changed.');

            return self::FAILURE;
        }

        $this->info('The administrator account was created. Sign in through the application login page.');

        return self::SUCCESS;
    }

    private function administratorExists(): bool
    {
        return User::query()->where('role', UserRole::Administrator->value)->exists();
    }
}
