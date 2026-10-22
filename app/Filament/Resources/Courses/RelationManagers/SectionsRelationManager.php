<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Filament\Resources\CourseSections\CourseSectionResource;
use App\Models\CourseSection;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Kurikulum';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title.id')
                ->label('Judul bab (Indonesia)')
                ->required()
                ->maxLength(255),

            TextInput::make('title.en')
                ->label('Judul bab (Inggris)')
                ->maxLength(255),

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
            ->modifyQueryUsing(fn ($query) => $query->withCount('lessons'))
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('title')
                    ->label('Bab')
                    ->formatStateUsing(fn ($state, CourseSection $record) => $record->getTranslation('title', 'id'))
                    ->wrap(),
                TextColumn::make('lessons_count')->label('Materi')->badge()->color('info'),
            ])
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()->label('Tambah bab'),
            ])
            ->recordActions([
                Action::make('manageLessons')
                    ->label('Kelola materi')
                    ->icon('heroicon-o-book-open')
                    ->url(fn (CourseSection $record) => CourseSectionResource::getUrl('edit', ['record' => $record])),

                EditAction::make(),

                DeleteAction::make()
                    ->disabled(fn (CourseSection $record) => $record->lessons()
                        ->whereHas('completions')
                        ->exists()
                        || $record->lessons()
                            ->whereHas('assignments.submissions')
                            ->exists())
                    ->tooltip(fn (CourseSection $record) => $record->lessons()
                        ->whereHas('completions')
                        ->exists()
                        || $record->lessons()
                            ->whereHas('assignments.submissions')
                            ->exists()
                        ? 'Bab yang sudah memiliki progres atau pengumpulan tugas tidak dapat dihapus.'
                        : null),
            ]);
    }
}
