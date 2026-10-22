<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Password;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('username')->label('Username')->searchable()->toggleable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => User::roleLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        User::ROLE_SUPERADMIN => 'danger',
                        User::ROLE_ADMIN => 'warning',
                        User::ROLE_LMS_ADMIN => 'info',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('last_login_at')
                    ->label('Login terakhir')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum pernah')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Peran')
                    ->options(User::roleLabels()),
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('toggleActive')
                    ->label(fn (User $record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (User $record) => $record->is_active ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        if ($record->id === auth()->id()) {
                            Notification::make()
                                ->danger()
                                ->title('Tidak dapat menonaktifkan akun sendiri')
                                ->send();

                            return;
                        }

                        if (
                            $record->is_active
                            && $record->isSuperadmin()
                            && User::query()->where('role', User::ROLE_SUPERADMIN)->where('is_active', true)->count() <= 1
                        ) {
                            Notification::make()
                                ->danger()
                                ->title('Superadmin terakhir tidak dapat dinonaktifkan')
                                ->send();

                            return;
                        }

                        $record->forceFill(['is_active' => ! $record->is_active])->save();

                        Notification::make()
                            ->success()
                            ->title($record->is_active ? 'Akun diaktifkan' : 'Akun dinonaktifkan')
                            ->send();
                    }),

                Action::make('sendResetLink')
                    ->label('Kirim tautan reset sandi')
                    ->icon('heroicon-o-key')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $status = Password::broker()->sendResetLink(['email' => $record->email]);

                        if ($status === Password::RESET_LINK_SENT) {
                            app(AuditLogger::class)->log('user.password_reset_sent', $record);

                            Notification::make()
                                ->success()
                                ->title('Tautan reset sandi dikirim')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->danger()
                            ->title('Gagal mengirim tautan reset sandi')
                            ->send();
                    }),
            ]);
    }
}
