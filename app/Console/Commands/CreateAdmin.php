<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Replaces reg.php, which was a public self-registration page that granted
 * full admin access to anyone who used it.
 *
 * Admin accounts are now created from the CLI only -- there is no public
 * registration route in this application.
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
                            {--name= }
                            {--username= }
                            {--email= }
                            {--role=superadmin}';

    protected $description = 'Create an administrator account (replaces the old public reg.php)';

    public function handle(): int
    {
        $name = $this->option('name') ?: text(
            label: 'Name',
            required: true,
        );

        $username = $this->option('username');

        $email = $this->option('email') ?: text(
            label: 'Email',
            required: true,
        );

        $role = $this->option('role');

        if (! in_array($role, User::roles(), true)) {
            $this->error('Role must be one of: '.implode(', ', User::roles()));

            return self::FAILURE;
        }

        $plain = password(
            label: 'Password (min 12 characters)',
            required: true,
        );

        $validator = Validator::make(
            ['name' => $name, 'username' => $username, 'email' => $email, 'password' => $plain],
            [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                // 12 chars minimum: the legacy admin password was "admin123".
                'password' => ['required', 'string', 'min:12'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $plain,  // hashed by the model's 'hashed' cast
            'role' => $role,
            'is_active' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Created {$role}: {$user->email}");

        return self::SUCCESS;
    }
}
