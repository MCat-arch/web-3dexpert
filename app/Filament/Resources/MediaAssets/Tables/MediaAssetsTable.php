<?php

namespace App\Filament\Resources\MediaAssets\Tables;

use App\Filament\Resources\MediaAssets\Schemas\MediaAssetInfolist;
use App\Models\MediaAsset;
use App\Support\MediaUsage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('file_name')->label('Nama berkas')->searchable()->wrap(),
                TextColumn::make('path')->label('Path')->searchable()->copyable()->limit(44),
                TextColumn::make('public_url')
                    ->label('URL')
                    ->state(fn (MediaAsset $record) => route('media.show', ['path' => $record->path]))
                    ->copyable()
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mime_type')->label('MIME')->badge()->toggleable(),
                TextColumn::make('size')
                    ->label('Ukuran')
                    ->formatStateUsing(fn ($state) => MediaAssetInfolist::humanSize((int) $state))
                    ->sortable(),
                TextColumn::make('created_at')->label('Diunggah')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                Filter::make('mime_group')
                    ->label('Jenis berkas')
                    ->schema([
                        Select::make('value')
                            ->label('Jenis')
                            ->options([
                                'image' => 'Gambar',
                                'pdf' => 'PDF',
                                'other' => 'Lainnya',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'image' => $query->where('mime_type', 'like', 'image/%'),
                            'pdf' => $query->where('mime_type', 'application/pdf'),
                            'other' => $query->where('mime_type', 'not like', 'image/%')
                                ->where('mime_type', '!=', 'application/pdf'),
                            default => $query,
                        };
                    }),

                Filter::make('created_range')
                    ->label('Tanggal unggah')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('open')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (MediaAsset $record) => route('media.show', ['path' => $record->path]))
                    ->openUrlInNewTab(),

                DeleteAction::make()
                    ->visible(fn () => auth()->user()?->isSuperadmin() ?? false)
                    ->disabled(fn (MediaAsset $record) => MediaUsage::isReferenced($record))
                    ->tooltip(fn (MediaAsset $record) => MediaUsage::isReferenced($record)
                        ? 'Aset masih dipakai. Hapus referensinya terlebih dahulu.'
                        : null),
            ]);
    }
}
