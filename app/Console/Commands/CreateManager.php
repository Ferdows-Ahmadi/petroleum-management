<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateManager extends Command
{
    protected $signature = 'app:create-manager {--name= : Manager display name (avoids Windows console code-page conversion)}';

    protected $description = 'Create the first general manager without demo users or default credentials';

    public function handle(): int
    {
        if (User::query()->where('role', UserRole::GeneralManager->value)->where('is_active', true)->exists()) {
            $this->error('An active general manager already exists. Use authenticated user administration.');

            return self::FAILURE;
        }
        $data = [
            'name' => $this->option('name') ?: $this->ask('Manager name'),
            'email' => $this->ask('Manager email'),
            'password' => $this->secret('Password (12+ characters, mixed case and numbers)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        return DB::transaction(function () use ($data): int {
            DB::table('operation_locks')->insertOrIgnore(['name' => 'user-bootstrap']);
            DB::table('operation_locks')->where('name', 'user-bootstrap')->lockForUpdate()->first();
            if (User::query()->where('role', UserRole::GeneralManager->value)->where('is_active', true)->exists()) {
                $this->error('An active general manager already exists.');

                return self::FAILURE;
            }
            User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
                'role' => UserRole::GeneralManager, 'is_active' => true]);
            $this->info('General manager created. No demonstration records were installed.');

            return self::SUCCESS;
        });
    }
}
