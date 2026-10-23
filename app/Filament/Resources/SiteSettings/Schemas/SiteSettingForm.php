<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use App\Models\SiteSetting;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SiteSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pengaturan situs')
                    ->columns(2)
                    ->schema([
                        TextInput::make('key')->required()->maxLength(255)->unique(ignoreRecord: true),
                        TextInput::make('group')->required()->maxLength(255),
                        Select::make('type')->options([
                            'string' => 'Teks',
                            'text' => 'Teks panjang',
                            'integer' => 'Angka',
                            'boolean' => 'Ya/Tidak',
                            'json' => 'JSON',
                        ])->required(),
                        Toggle::make('is_translatable'),
                        TextInput::make('value')->maxLength(65535)->columnSpanFull(),
                        KeyValue::make('value_translations')
                            ->label('Terjemahan')
                            ->keyLabel('Kode bahasa (id/en)')
                            ->valueLabel('Nilai')
                            ->default([])
                            ->formatStateUsing(fn ($state, ?SiteSetting $record) => $record?->getTranslations('value_translations') ?? [])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
