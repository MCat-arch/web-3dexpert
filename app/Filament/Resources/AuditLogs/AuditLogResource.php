<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Filament\Support\NavGroup;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditLogResource extends AdminResource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::SYSTEM;

    protected static ?int $navigationSort = 95;

    protected static string $accessArea = User::AREA_AUDIT;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'Log Audit';

    protected static ?string $pluralModelLabel = 'Log Audit';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['actor', 'subject']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ringkasan')
                ->columns(3)
                ->schema([
                    TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s'),
                    TextEntry::make('event')->label('Peristiwa')->badge(),
                    TextEntry::make('ip_address')->label('IP')->placeholder('-'),
                    TextEntry::make('actor.name')->label('Aktor')->placeholder('Sistem'),
                    TextEntry::make('subject')
                        ->label('Subjek')
                        ->state(fn (AuditLog $record) => $record->subjectLabel()),
                    TextEntry::make('description')->label('Deskripsi')->placeholder('-')->columnSpanFull(),
                ]),

            Section::make('Nilai Lama')
                ->collapsed()
                ->schema([
                    TextEntry::make('old_values')
                        ->hiddenLabel()
                        ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                        ->columnSpanFull(),
                ]),

            Section::make('Nilai Baru')
                ->collapsed()
                ->schema([
                    TextEntry::make('new_values')
                        ->hiddenLabel()
                        ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                        ->columnSpanFull(),
                ]),

            Section::make('Perangkat')
                ->collapsed()
                ->schema([
                    TextEntry::make('user_agent')->label('User agent')->placeholder('-')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['actor', 'subject']))
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('event')->label('Peristiwa')->badge()->searchable(),
                TextColumn::make('actor.name')->label('Aktor')->placeholder('Sistem')->searchable(),
                TextColumn::make('subject_label')
                    ->label('Subjek')
                    ->state(fn (AuditLog $record) => $record->subjectLabel())
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('description')->label('Deskripsi')->limit(60)->toggleable(),
                TextColumn::make('ip_address')->label('IP')->toggleable()->fontFamily('mono'),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Peristiwa')
                    ->options(fn () => AuditLog::query()
                        ->distinct()
                        ->orderBy('event')
                        ->pluck('event', 'event')
                        ->all()),
                SelectFilter::make('actor_id')
                    ->label('Aktor')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $actorId) => $q
                            ->where('actor_type', User::class)
                            ->where('actor_id', $actorId),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }
}
