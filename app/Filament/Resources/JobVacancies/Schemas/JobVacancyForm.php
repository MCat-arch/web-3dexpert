<?php

namespace App\Filament\Resources\JobVacancies\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobVacancyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Lowongan')
                ->columns(2)
                ->schema([
                    TextInput::make('title.id')
                        ->label('Judul (Indonesia)')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('title.en')
                        ->label('Judul (Inggris)')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->maxLength(255)
                        ->helperText('Kosongkan untuk dibuat otomatis dari judul.')
                        ->unique(ignoreRecord: true),

                    TextInput::make('department')
                        ->label('Departemen')
                        ->maxLength(255),

                    TextInput::make('location')
                        ->label('Lokasi')
                        ->maxLength(255),

                    TextInput::make('employment_type')
                        ->label('Jenis pekerjaan')
                        ->maxLength(255)
                        ->placeholder('Full Time / Internship / Contract'),

                    DateTimePicker::make('closes_at')
                        ->label('Batas lamaran')
                        ->seconds(false)
                        ->helperText('Lowongan tidak dapat dipublikasikan jika tanggal ini sudah lewat.'),

                    TextInput::make('contact_url')
                        ->label('URL kontak / WhatsApp')
                        ->url()
                        ->maxLength(2048)
                        ->columnSpanFull(),
                ]),

            Section::make('Deskripsi')
                ->schema([
                    Textarea::make('requirement.id')
                        ->label('Persyaratan (Indonesia)')
                        ->rows(5)
                        ->required(),

                    Textarea::make('requirement.en')
                        ->label('Persyaratan (Inggris)')
                        ->rows(5)
                        ->required(),

                    Textarea::make('description.id')
                        ->label('Deskripsi (Indonesia)')
                        ->rows(6),

                    Textarea::make('description.en')
                        ->label('Deskripsi (Inggris)')
                        ->rows(6),
                ]),

            Section::make('Publikasi')
                ->schema([
                    Toggle::make('is_published')
                        ->label('Tampilkan di situs publik')
                        ->default(false),
                ]),
        ]);
    }
}
