<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Password;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('enrollments'))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('phone')->label('Telepon')->searchable()->toggleable(),
                IconColumn::make('email_verified_at')
                    ->label('Terverifikasi')
                    ->boolean()
                    ->state(fn (Customer $record) => $record->email_verified_at !== null),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('enrollments_count')->label('Kursus')->badge()->color('info'),
                TextColumn::make('last_login_at')
                    ->label('Login terakhir')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum pernah')
                    ->sortable(),
                TextColumn::make('deleted_at')->label('Dihapus')->dateTime('d M Y H:i')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Aktif'),
                TernaryFilter::make('email_verified_at')
                    ->label('Terverifikasi')
                    ->nullable()
                    ->queries(
                        fn ($query) => $query->whereNotNull('email_verified_at'),
                        fn ($query) => $query->whereNull('email_verified_at'),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('toggleActive')
                    ->label(fn (Customer $record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (Customer $record) => $record->is_active ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (Customer $record) => $record->is_active ? 'danger' : 'success')
                    ->visible(fn (Customer $record) => ! $record->trashed())
                    ->requiresConfirmation()
                    ->action(function (Customer $record) {
                        $record->forceFill(['is_active' => ! $record->is_active])->save();

                        $record->is_active
                            ? app(AuditLogger::class)->reactivated($record, 'customer.reactivated')
                            : app(AuditLogger::class)->deactivated($record, 'customer.deactivated');

                        Notification::make()
                            ->success()
                            ->title($record->is_active ? 'Pelanggan diaktifkan' : 'Pelanggan dinonaktifkan')
                            ->send();
                    }),

                Action::make('sendResetLink')
                    ->label('Kirim tautan reset sandi')
                    ->icon('heroicon-o-key')
                    ->visible(fn (Customer $record) => ! $record->trashed())
                    ->requiresConfirmation()
                    ->action(function (Customer $record) {
                        $status = Password::broker('customers')->sendResetLink(['email' => $record->email]);

                        if ($status === Password::RESET_LINK_SENT) {
                            app(AuditLogger::class)->log('customer.password_reset_sent', $record);

                            Notification::make()->success()->title('Tautan reset sandi dikirim')->send();

                            return;
                        }

                        Notification::make()->danger()->title('Gagal mengirim tautan reset sandi')->send();
                    }),
            ]);
    }
}
