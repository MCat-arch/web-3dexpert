<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\ProjectUpdate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Progress entries, managed inline on the project.
 *
 * Every note here is CUSTOMER-VISIBLE. There is deliberately no
 * internal-notes field: if staff need private commentary, add a separate
 * column and exclude it from the lookup payload, the way P6 handles
 * job_applications.internal_notes. One shared field is how a private remark
 * reaches a customer.
 */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Riwayat Progres';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('stage_name')->label('Nama tahap')->required(),

            Select::make('status')
                ->label('Status tahap')
                ->options(
                    collect(ProjectUpdate::statuses())
                        ->mapWithKeys(fn (string $s) => [$s => __('tracker.update_'.$s)])
                        ->all()
                )
                ->default(ProjectUpdate::STATUS_DONE)
                ->required(),

            DatePicker::make('occurred_at')->label('Tanggal')->default(now())->required(),

            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0)->required(),

            Textarea::make('note')
                ->label('Catatan untuk pelanggan')
                ->rows(3)
                ->helperText('Terlihat oleh pelanggan di halaman pelacakan.')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('stage_name')->label('Tahap'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('tracker.update_'.$state)),
                TextColumn::make('occurred_at')->label('Tanggal')->date('d M Y'),
                TextColumn::make('note')->label('Catatan')->wrap()->limit(60),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
