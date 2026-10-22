<?php

namespace App\Filament\Resources\Courses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['sections', 'lessons', 'enrollments']))
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->formatStateUsing(fn ($state, $record) => $record->getTranslation('title', 'id'))
                    ->searchable()
                    ->wrap(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->copyable()
                    ->color('gray'),

                TextColumn::make('level')
                    ->label('Level')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'beginner' => 'Pemula',
                        'intermediate' => 'Menengah',
                        'advanced' => 'Lanjutan',
                        default => '-',
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'beginner' => 'success',
                        'intermediate' => 'warning',
                        'advanced' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn ($state) => (float) $state <= 0
                        ? 'Gratis'
                        : 'Rp '.number_format((float) $state, 0, ',', '.')),

                TextColumn::make('estimated_minutes')
                    ->label('Durasi')
                    ->formatStateUsing(fn ($state) => $state ? $state.' menit' : '-'),

                TextColumn::make('sections_count')
                    ->label('Bab')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('lessons_count')
                    ->label('Materi')
                    ->badge()
                    ->color(fn ($state, $record) => $record->has_lms && (int) $state === 0 ? 'warning' : 'info')
                    ->sortable(),

                TextColumn::make('enrollments_count')
                    ->label('Siswa')
                    ->badge()
                    ->color('success')
                    ->toggleable(),

                IconColumn::make('has_lms')
                    ->label('LMS')
                    ->boolean(),

                IconColumn::make('is_published')
                    ->label('Terbit')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Terbit'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
