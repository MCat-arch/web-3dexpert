<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListAdmins extends Command
{
    protected $signature = 'app:list-admins';

    protected $description = 'List administrator login identifiers without exposing passwords';

    public function handle(): int
    {
        $admins = User::query()
            ->select(['id', 'name', 'username', 'email', 'role', 'is_active'])
            ->whereIn('role', User::roles())
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'ID' => $user->id,
                'Name' => $user->name,
                'Username' => $user->username ?: '-',
                'Email' => $user->email,
                'Role' => $user->role,
                'Active' => $user->is_active ? 'yes' : 'no',
            ]);

        if ($admins->isEmpty()) {
            $this->warn('No administrator accounts found.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Username', 'Email', 'Role', 'Active'], $admins);

        return self::SUCCESS;
    }
}
