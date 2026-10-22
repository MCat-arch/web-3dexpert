<?php

namespace App\Filament\Resources\MediaAssets\Schemas;

use App\Models\MediaAsset;
use App\Support\MediaUsage;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MediaAssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Berkas')
                ->columns(3)
                ->schema([
                    TextEntry::make('file_name')->label('Nama berkas')->copyable(),
                    TextEntry::make('path')->label('Path')->copyable()->columnSpan(2),
                    TextEntry::make('mime_type')->label('Tipe MIME'),
                    TextEntry::make('size')
                        ->label('Ukuran')
                        ->formatStateUsing(fn ($state) => self::humanSize((int) $state)),
                    TextEntry::make('created_at')->label('Diunggah')->dateTime('d M Y H:i'),
                    TextEntry::make('sha256')->label('SHA-256')->copyable()->columnSpanFull(),
                ]),

            Section::make('Penggunaan')
                ->schema([
                    TextEntry::make('usages')
                        ->label('Direferensikan oleh')
                        ->state(fn (MediaAsset $record) => collect(MediaUsage::references($record))
                            ->map(fn (int $count, string $label) => $label.' ('.$count.')')
                            ->values()
                            ->all())
                        ->listWithLineBreaks()
                        ->placeholder('Tidak direferensikan. Aset ini dapat dihapus superadmin.'),
                ]),
        ]);
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 2, ',', '.').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return $bytes.' B';
    }
}
