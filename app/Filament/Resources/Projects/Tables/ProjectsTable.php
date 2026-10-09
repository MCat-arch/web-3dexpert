<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Models\Project;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('project_code')
                    ->label('Kode')
                    ->searchable()
                    // Staff send this to the customer, so one click to copy.
                    ->copyable()
                    ->copyMessage('Kode proyek disalin')
                    ->fontFamily('mono'),

                TextColumn::make('title')->label('Proyek')->searchable()->wrap(),
                TextColumn::make('customer_name')->label('Pelanggan')->searchable(),

                TextColumn::make('customer_phone')
                    ->label('WhatsApp')
                    ->searchable()
                    ->toggleable()
                    ->copyable(),

                TextColumn::make('current_stage')->label('Tahap')->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('tracker.status_'.$state))
                    ->color(fn (string $state) => match ($state) {
                        Project::STATUS_IN_PROGRESS => 'success',
                        Project::STATUS_ON_HOLD => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_visible')->label('Tampil')->boolean(),

                TextColumn::make('updates_count')
                    ->label('Progres')
                    ->counts('updates')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(Project::statuses())
                        ->mapWithKeys(fn (string $s) => [$s => __('tracker.status_'.$s)])
                        ->all()
                ),

                TernaryFilter::make('is_visible')->label('Tampil di pelacakan'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
