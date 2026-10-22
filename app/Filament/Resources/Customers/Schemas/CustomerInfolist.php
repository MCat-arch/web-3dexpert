<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Nama'),
                    TextEntry::make('email')->label('Email')->copyable(),
                    TextEntry::make('phone')->label('Telepon / WhatsApp')->placeholder('-'),
                    IconEntry::make('email_verified_at')
                        ->label('Email terverifikasi')
                        ->boolean()
                        ->state(fn (Customer $record) => $record->email_verified_at !== null),
                    IconEntry::make('is_active')->label('Aktif')->boolean(),
                    TextEntry::make('last_login_at')
                        ->label('Login terakhir')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Belum pernah'),
                    TextEntry::make('created_at')->label('Terdaftar')->dateTime('d M Y H:i'),
                    TextEntry::make('enrollments_count')
                        ->label('Kursus diikuti')
                        ->state(fn (Customer $record) => $record->enrollments()->count()),
                    TextEntry::make('deleted_at')
                        ->label('Dihapus pada')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Tidak dihapus'),
                ]),
        ]);
    }
}
