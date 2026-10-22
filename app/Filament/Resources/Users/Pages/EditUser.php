<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Ubah Pengguna Staf';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $actor = auth()->user();

        $newRole = (string) ($data['role'] ?? $record->role);
        $newActive = (bool) ($data['is_active'] ?? $record->is_active);

        if ($actor?->id === $record->id) {
            if ($newRole !== $record->role) {
                throw ValidationException::withMessages([
                    'data.role' => 'Anda tidak dapat mengubah peran akun sendiri.',
                ]);
            }

            if (! $newActive) {
                throw ValidationException::withMessages([
                    'data.is_active' => 'Anda tidak dapat menonaktifkan akun sendiri.',
                ]);
            }
        }

        $demotingLastSuperadmin = $record->isSuperadmin()
            && $record->is_active
            && ($newRole !== User::ROLE_SUPERADMIN || ! $newActive)
            && User::query()
                ->where('role', User::ROLE_SUPERADMIN)
                ->where('is_active', true)
                ->count() <= 1;

        if ($demotingLastSuperadmin) {
            $errorKey = $newRole !== User::ROLE_SUPERADMIN ? 'data.role' : 'data.is_active';

            throw ValidationException::withMessages([
                $errorKey => 'Superadmin aktif terakhir tidak dapat diturunkan atau dinonaktifkan.',
            ]);
        }

        return $data;
    }
}
