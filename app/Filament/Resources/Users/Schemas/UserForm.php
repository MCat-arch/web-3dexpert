<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama lengkap')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('username')
                        ->label('Username (opsional)')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->nullable(),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('role')
                        ->label('Peran')
                        ->options(User::roleLabels())
                        ->required()
                        ->disabled(fn (?User $record) => $record?->id === auth()->id())
                        ->dehydrated(),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->helperText('Nonaktifkan untuk mencabut akses tanpa menghapus akun.'),

                    TextInput::make('password')
                        ->label('Kata sandi')
                        ->password()
                        ->revealable()
                        ->minLength(12)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Minimal 12 karakter. Kosongkan saat mengedit untuk mempertahankan kata sandi lama.'),

                    TextInput::make('last_login_at')
                        ->label('Login terakhir')
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn ($state) => $state?->format('d M Y H:i') ?? 'Belum pernah login')
                        ->visibleOn('edit'),
                ]),
        ]);
    }
}
