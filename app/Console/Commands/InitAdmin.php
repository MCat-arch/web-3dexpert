<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

/**
 * Idempotent bootstrap for the first superadmin.
 *
 * The command intentionally refuses to silently overwrite an existing
 * superadmin. Pass --force when the account must be repaired or its password
 * reset. Credentials can come from ADMIN_* environment variables, CLI options,
 * or interactive prompts; there is no default password.
 */
class InitAdmin extends Command
{
    protected $signature = 'app:init-admin
                            {--name= : Nama lengkap}
                            {--username= : Username opsional}
                            {--email= : Email login}
                            {--password= : Kata sandi minimal 12 karakter}
                            {--force : Perbarui akun yang sudah ada}';

    protected $description = 'Initialize the initial superadmin account safely';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: env('ADMIN_EMAIL') ?: '');
        $name = (string) ($this->option('name') ?: env('ADMIN_NAME') ?: '');
        $username = $this->option('username') ?: env('ADMIN_USERNAME');
        $plainPassword = $this->option('password') ?: env('ADMIN_PASSWORD');

        if ($email === '') {
            if (! $this->input->isInteractive()) {
                $this->error('ADMIN_EMAIL atau --email wajib diisi.');

                return self::FAILURE;
            }

            $email = (string) text(label: 'Email', required: true);
        }

        $existing = User::query()->where('email', $email)->first();
        $force = (bool) $this->option('force');

        if ($existing && ! $force) {
            if (! $existing->isSuperadmin()) {
                $this->error("Email {$email} sudah dipakai role {$existing->role}. Gunakan --force hanya jika akun ini memang harus dijadikan superadmin.");

                return self::FAILURE;
            }

            $this->info("Superadmin {$existing->email} sudah ada. Tidak ada perubahan.");
            $this->showLoginHint($existing);

            return self::SUCCESS;
        }

        if ($name === '') {
            if (! $this->input->isInteractive()) {
                $this->error('ADMIN_NAME atau --name wajib diisi.');

                return self::FAILURE;
            }

            $name = (string) text(label: 'Nama lengkap', required: true);
        }

        // A new account always needs a password. For an existing account,
        // --force without a password only repairs role/status and keeps the
        // current password.
        if (blank($plainPassword) && ! $existing) {
            if (! $this->input->isInteractive()) {
                $this->error('ADMIN_PASSWORD atau --password wajib diisi untuk akun baru.');

                return self::FAILURE;
            }

            $plainPassword = (string) promptPassword(
                label: 'Password (min 12 karakter)',
                required: true,
            );
        }

        $uniqueSuffix = $existing ? ','.$existing->getKey() : '';

        $validator = Validator::make(
            [
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $plainPassword,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['nullable', 'string', 'max:255', 'unique:users,username'.$uniqueSuffix],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'.$uniqueSuffix],
                'password' => ['nullable', 'string', 'min:12'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($existing) {
            $attributes = [
                'name' => $name,
                'role' => User::ROLE_SUPERADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ];

            if (filled($username)) {
                $attributes['username'] = $username;
            }

            if (filled($plainPassword)) {
                $attributes['password'] = $plainPassword;
            }

            $existing->forceFill($attributes)->save();

            $this->info("Superadmin diperbarui: {$existing->email}");

            if (blank($plainPassword)) {
                $this->line('Kata sandi lama dipertahankan.');
            }

            $this->showLoginHint($existing);

            return self::SUCCESS;
        }

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $plainPassword,
            'role' => User::ROLE_SUPERADMIN,
            'is_active' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Superadmin dibuat: {$user->email}");
        $this->showLoginHint($user);

        return self::SUCCESS;
    }

    private function showLoginHint(User $user): void
    {
        $this->line('Login di: '.url('/admin/login'));
        $this->line('Gunakan email '.$user->email.($user->username ? " atau username {$user->username}" : '').'.');
    }
}
