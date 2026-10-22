<?php

namespace App\Filament\Resources\Courses\Schemas;

use App\Models\Course;
use App\Models\User;
use App\Support\MediaUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Pamflet / Banner Kursus')
                ->schema([
                    MediaUpload::field('cover_path', 'courses', ['image/jpeg', 'image/png', 'image/webp'], 5120, true)
                        ->label('Pamflet / Poster Kursus')
                        ->helperText('Unggah pamflet atau poster resmi kursus (JPG, PNG, WebP maks 5MB).'),
                ]),

            Section::make('Judul dan Deskripsi')
                ->schema([
                    TextInput::make('title.id')
                        ->label('Judul Kursus (Bahasa Indonesia)')
                        ->required()
                        ->maxLength(255)
                        ->formatStateUsing(fn ($state, ?Course $record) => $record?->getTranslation('title', 'id') ?? ''),

                    Textarea::make('description.id')
                        ->label('Deskripsi Kursus (Bahasa Indonesia)')
                        ->rows(6)
                        ->formatStateUsing(fn ($state, ?Course $record) => $record?->getTranslation('description', 'id') ?? ''),
                ]),

            Section::make('Detail Kursus')
                ->columns(2)
                ->schema([
                    TextInput::make('slug')
                        ->label('Slug URL')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Kosongkan = otomatis dari judul Indonesia.')
                        ->nullable(),

                    TextInput::make('category')
                        ->label('Kategori')
                        ->maxLength(100)
                        ->placeholder('Contoh: Desain 3D, Arsitektur & Interior')
                        ->nullable(),

                    Select::make('level')
                        ->label('Level')
                        ->options([
                            'beginner' => 'Pemula (Beginner)',
                            'intermediate' => 'Menengah (Intermediate)',
                            'advanced' => 'Lanjutan / Mentoring (Advanced)',
                        ])
                        ->nullable()
                        ->live(),

                    TextInput::make('price')
                        ->label('Harga')
                        ->numeric()
                        ->default(0)
                        ->prefix('Rp')
                        ->disabled(fn () => ! auth()->user()?->can(User::AREA_PAYMENTS))
                        ->dehydrated(fn () => (bool) auth()->user()?->can(User::AREA_PAYMENTS))
                        ->helperText('0 = Gratis. Hanya admin yang dapat mengubah harga.'),

                    TextInput::make('estimated_minutes')
                        ->label('Estimasi Waktu (menit)')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->helperText('Kosongkan untuk kursus berbasis proyek.'),

                    Toggle::make('has_lms')
                        ->label('Pakai LMS (Video Player Online)')
                        ->default(true)
                        ->helperText('Aktifkan untuk kursus online dengan video/materi di website. Nonaktifkan untuk program mentoring langsung (advanced).')
                        ->live(),

                    Toggle::make('is_published')
                        ->label('Terbit')
                        ->default(false)
                        ->helperText('Kursus hanya tampil di situs jika diterbitkan.'),

                    Placeholder::make('curriculum_hint')
                        ->label('Kurikulum')
                        ->content('Simpan kursus ini, lalu kelola Bab dan Materi pada bagian Kurikulum di bawah formulir.')
                        ->visible(fn ($get) => (bool) $get('has_lms'))
                        ->columnSpanFull(),
                ]),

            Section::make('Konfigurasi Mentoring (Advanced)')
                ->description('Digunakan jika LMS tidak aktif. Peserta akan diarahkan ke WhatsApp untuk daftar.')
                ->visible(fn ($get) => ! $get('has_lms') && auth()->user()?->can(User::AREA_CONTENT))
                ->schema([
                    TextInput::make('whatsapp_cta')
                        ->label('Link WhatsApp Khusus')
                        ->url()
                        ->placeholder('https://wa.me/628xxxx?text=...')
                        ->helperText('Kosongkan = gunakan nomor WhatsApp utama perusahaan.')
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
