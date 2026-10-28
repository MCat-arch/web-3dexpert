<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\ProjectAttachment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Files shown on the public tracker.
 *
 * Everything here is customer-visible the moment it is saved, so there is no
 * "internal attachment" kind. SECURITY: files land on the private
 * 'attachments' disk and are streamed through a signed, expiring URL; the
 * tracker never links to a path directly.
 */
class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Lampiran & Hasil Akhir';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Berkas')
                ->disk(ProjectAttachment::DISK)
                ->directory('projects')
                // Stores the browser filename in the column of the same name,
                // so staff and customers see "denah-lantai-2.pdf" rather than
                // the random storage name.
                ->storeFileNamesIn('original_name')
                ->acceptedFileTypes([
                    'image/jpeg',
                    'image/png',
                    'application/pdf',
                    'application/zip',
                    'application/x-zip-compressed',
                ])
                ->maxSize(ProjectAttachment::MAX_KB)
                ->helperText('JPG, PNG, PDF, atau ZIP. Maksimal 5 MB.')
                ->required()
                ->columnSpanFull(),

            TextInput::make('title')
                ->label('Judul berkas')
                ->maxLength(120)
                ->helperText('Opsional. Jika kosong, nama file asli yang dipakai.'),

            Select::make('kind')
                ->label('Jenis')
                ->options([
                    ProjectAttachment::KIND_PROGRESS => 'Progres (tampil selama proyek berjalan)',
                    ProjectAttachment::KIND_DELIVERABLE => 'Hasil akhir (tampil saat proyek selesai)',
                ])
                ->default(ProjectAttachment::KIND_PROGRESS)
                ->required(),

            Select::make('project_update_id')
                ->label('Tahap terkait')
                ->placeholder('Tidak terkait tahap tertentu')
                ->options(fn (): array => $this->getOwnerRecord()
                    ->updates()
                    ->pluck('stage_name', 'id')
                    ->all())
                ->helperText('Jika dipilih, berkas muncul di dalam tahap tersebut pada riwayat progres.'),

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
            ->columns([
                TextColumn::make('display_name')
                    ->label('Berkas')
                    ->state(fn (ProjectAttachment $record): string => $record->displayName())
                    ->description(fn (ProjectAttachment $record): string => $record->typeLabel().' Â· '.$record->sizeLabel())
                    ->wrap(),

                TextColumn::make('kind')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === ProjectAttachment::KIND_DELIVERABLE
                        ? 'Hasil akhir'
                        : 'Progres')
                    ->color(fn (string $state): string => $state === ProjectAttachment::KIND_DELIVERABLE
                        ? 'success'
                        : 'warning'),

                TextColumn::make('stage.stage_name')
                    ->label('Tahap')
                    ->placeholder('â€”'),

                TextColumn::make('sort_order')->label('#')->sortable(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
