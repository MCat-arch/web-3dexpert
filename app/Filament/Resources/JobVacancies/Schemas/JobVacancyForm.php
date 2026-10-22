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
                        ->label('Judul')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->maxLength(255)
                        ->helperText('Kosongkan untuk dibuat otomatis dari judul.')
                        ->unique(ignoreRecord: true),

                    TextInput::make('department')
                        ->label('Departemen')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('location')
                        ->label('Lokasi')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('employment_type')
                        ->label('Jenis pekerjaan')
                        ->required()
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
                        ->label('Persyaratan')
                        ->rows(5)
                        ->required(),

                    Textarea::make('description.id')
                        ->label('Deskripsi')
                        ->rows(6),
                ]),

            Section::make('Publikasi')
                ->schema([
                    Toggle::make('is_published')
                        ->label('Tampilkan di situs publik')
                        ->default(false)
                        ->helperText('Aktifkan agar lowongan muncul di halaman Karier publik.'),
                ]),
        ]);
    }
}
