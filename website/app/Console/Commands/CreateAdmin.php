<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Create a protected Super Admin using an interactive password prompt';

    public function handle(): int
    {
        $name = $this->ask('Name');
        $email = strtolower((string) $this->ask('Email'));
        $password = $this->secret('Password (12+ characters, upper/lowercase and a number)');
        $confirmation = $this->secret('Confirm password');
        $v = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], ['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers(), 'max:255']]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $message) {
                $this->error($message);
            }

return self::FAILURE;
        }
        $role = Role::where('is_super', true)->firstOrFail();
        User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role_id' => $role->id, 'is_active' => true]);
        $this->info('Super Admin created. Sign in at /admin/login.');

        return self::SUCCESS;
    }
}
