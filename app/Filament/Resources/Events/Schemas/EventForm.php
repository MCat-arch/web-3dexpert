<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Event;
use App\Support\MediaUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pamflet / Banner Acara')
                ->schema([
                    MediaUpload::field('cover_path', 'events', ['image/jpeg', 'image/png', 'image/webp'], 5120, true)
                        ->label('Pamflet / Poster Acara')
                        ->helperText('Unggah pamflet atau poster resmi acara (JPG, PNG, WebP maks 5MB).'),
                ]),

            Section::make('Judul dan Deskripsi')
                ->schema([
                    TextInput::make('title.id')
                        ->label('Judul Acara ')
                        ->required()
                        ->formatStateUsing(fn ($state, ?Event $record) => $record?->getTranslation('title', 'id') ?? ''),
                    Textarea::make('description.id')
                        ->label('Deskripsi Acara ')
                        ->rows(5)
                        ->formatStateUsing(fn ($state, ?Event $record) => $record?->getTranslation('description', 'id') ?? ''),
                ]),

            Section::make('Kustomisasi Bahasa Inggris (Opsional)')
                ->collapsed()
                ->description('Kosongkan bagian ini jika ingin sistem otomatis menerjemahkan dari Bahasa Indonesia.')
                ->schema([
                    TextInput::make('title.en')
                        ->label('Title (English)')
                        ->placeholder('Otomatis diterjemahkan jika kosong...')
                        ->formatStateUsing(fn ($state, ?Event $record) => $record?->getTranslation('title', 'en') ?? ''),
                    Textarea::make('description.en')
                        ->label('Description (English)')
                        ->placeholder('Otomatis diterjemahkan jika kosong...')
                        ->rows(4)
                        ->formatStateUsing(fn ($state, ?Event $record) => $record?->getTranslation('description', 'en') ?? ''),
                ]),

            Section::make('Waktu dan lokasi')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('starts_at')->label('Mulai')->required()->seconds(false),
                    DateTimePicker::make('ends_at')->label('Selesai')->seconds(false),
                    Select::make('location_type')
                        ->label('Tipe lokasi')
                        ->options([
                            Event::LOCATION_OFFLINE => 'Offline',
                            Event::LOCATION_ONLINE => 'Online',
                            Event::LOCATION_HYBRID => 'Online & offline',
                        ])
                        ->default(Event::LOCATION_OFFLINE)
                        ->required()
                        ->live(),
                    TextInput::make('venue')
                        ->label('Tempat')
                        ->visible(fn ($get) => $get('location_type') !== Event::LOCATION_ONLINE),
                    TextInput::make('online_url')
                        ->label('Tautan online')
                        ->url()
                        ->visible(fn ($get) => $get('location_type') !== Event::LOCATION_OFFLINE),
                ]),

            Section::make('Kuota dan pendaftaran')
                ->columns(2)
                ->schema([
                    TextInput::make('capacity')
                        ->label('Kuota')
                        ->numeric()
                        ->minValue(0)
                        // NULL means unlimited, which is why this is nullable
                        // rather than defaulting to 0 -- 0 is a real value
                        // meaning "no seats".
                        ->helperText('Kosongkan untuk kuota tidak terbatas.'),

                    TextInput::make('price')
                        ->label('Harga')
                        ->numeric()
                        ->default(0)
                        ->prefix('Rp')
                        ->helperText('0 = gratis. Pembayaran menyusul di fase P5b.'),

                    DateTimePicker::make('registration_opens_at')
                        ->label('Pendaftaran dibuka')
                        ->seconds(false),
                    DateTimePicker::make('registration_closes_at')
                        ->label('Pendaftaran ditutup')
                        ->seconds(false)
                        ->after('registration_opens_at'),

                    Toggle::make('is_published')
                        ->label('Terbit')
                        ->default(false)
                        ->helperText('Acara hanya tampil di situs jika terbit.'),
                ]),

            Section::make('Kursi terpakai')
                ->schema([
                    TextInput::make('seats_taken')
                        ->label('Kursi terpakai')
                        ->disabled()
                        ->dehydrated(false)
                        /*
                         * Read-only on purpose. This counter is maintained by
                         * an atomic conditional UPDATE in EnrollInEvent; a
                         * staff edit here would corrupt seat inventory and
                         * either oversell the event or hide open seats.
                         * Corrections belong in the O6 reconciliation job,
                         * which audit-logs them.
                         */
                        ->helperText('Dikelola otomatis oleh sistem pendaftaran. Tidak bisa diubah manual.'),
                ])
                ->visibleOn('edit'),
        ]);
    }
}
