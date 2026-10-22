<?php

namespace App\Filament\Resources\CourseSections\RelationManagers;

use App\Models\CourseLesson;
use App\Support\MediaUpload;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LessonsRelationManager extends RelationManager
{
    protected static string $relationship = 'lessons';

    protected static ?string $title = 'Materi';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title.id')
                ->label('Judul materi (Indonesia)')
                ->required()
                ->maxLength(255),

            TextInput::make('title.en')
                ->label('Judul materi (Inggris)')
                ->maxLength(255),

            Select::make('content_type')
                ->label('Jenis materi')
                ->options([
                    'video' => 'Video YouTube',
                    'text' => 'Teks',
                    'file' => 'Berkas unduhan',
                ])
                ->default('video')
                ->required()
                ->live(),

            TextInput::make('youtube_url')
                ->label('URL YouTube')
                ->url()
                ->maxLength(2048)
                ->required(fn ($get) => $get('content_type') === 'video')
                ->visible(fn ($get) => $get('content_type') === 'video')
                ->helperText('ID video diekstrak otomatis saat disimpan. URL dapat diganti kapan saja.'),

            TextInput::make('youtube_id')
                ->label('YouTube ID')
                ->disabled()
                ->dehydrated(false)
                ->visible(fn (?CourseLesson $record) => filled($record?->youtube_id))
                ->helperText('Dibuat otomatis dari URL.'),

            Textarea::make('body.id')
                ->label('Isi materi (Indonesia)')
                ->rows(8)
                ->visible(fn ($get) => $get('content_type') === 'text'),

            Textarea::make('body.en')
                ->label('Isi materi (Inggris)')
                ->rows(8)
                ->visible(fn ($get) => $get('content_type') === 'text'),

            MediaUpload::assetIdField(
                'attachment_media_id',
                'lesson-files',
                [
                    'application/pdf',
                    'application/zip',
                    'application/x-zip-compressed',
                    'image/jpeg',
                    'image/png',
                ],
                20480,
            )
                ->label('Berkas materi')
                ->visible(fn ($get) => $get('content_type') === 'file')
                ->helperText('PDF, ZIP, JPG, atau PNG maksimal 20 MB.'),

            TextInput::make('duration_seconds')
                ->label('Durasi (detik)')
                ->numeric()
                ->minValue(0)
                ->nullable()
                ->helperText(fn (?CourseLesson $record) => $record?->durationFormatted()
                    ? 'Format saat ini: '.$record->durationFormatted()
                    : 'Opsional. Contoh: 600 = 10:00.'),

            Toggle::make('is_free_preview')
                ->label('Pratinjau gratis')
                ->helperText('Materi ini dapat ditonton tanpa mendaftar kursus.'),

            TextInput::make('sort_order')
                ->label('Urutan')
                ->numeric()
                ->default(0)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'completions',
                'assignments as assignments_with_submissions_count' => fn ($q) => $q->whereHas('submissions'),
            ]))
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('title')
                    ->label('Materi')
                    ->formatStateUsing(fn ($state, CourseLesson $record) => $record->getTranslation('title', 'id'))
                    ->wrap(),
                TextColumn::make('content_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'video' => 'Video',
                        'text' => 'Teks',
                        'file' => 'Berkas',
                        default => $state,
                    }),
                TextColumn::make('duration_seconds')
                    ->label('Durasi')
                    ->formatStateUsing(fn ($state, CourseLesson $record) => $record->durationFormatted() ?? '-'),
                IconColumn::make('is_free_preview')->label('Pratinjau')->boolean(),
            ])
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()->label('Tambah materi'),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->disabled(fn (CourseLesson $record) => (int) $record->completions_count > 0
                        || (int) $record->assignments_with_submissions_count > 0)
                    ->tooltip(fn (CourseLesson $record) => (int) $record->completions_count > 0
                        || (int) $record->assignments_with_submissions_count > 0
                        ? 'Materi yang sudah memiliki progres atau pengumpulan tugas tidak dapat dihapus.'
                        : null),
            ]);
    }
}
