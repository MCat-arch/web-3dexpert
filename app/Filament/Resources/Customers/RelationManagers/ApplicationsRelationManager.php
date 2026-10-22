<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\JobApplication;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'jobApplications';

    protected static ?string $title = 'Lamaran Kerja';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('vacancy'))
            ->columns([
                TextColumn::make('vacancy.title')
                    ->label('Posisi')
                    ->formatStateUsing(fn ($state, JobApplication $record) => $record->vacancy?->getTranslation('title', 'id'))
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('careers.status_'.$state)),
                TextColumn::make('submitted_at')->label('Dikirim')->dateTime('d M Y H:i'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (JobApplication $record) => JobApplicationResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
