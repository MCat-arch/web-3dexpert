<?php

namespace App\Filament\Resources\ValuePropositions\Schemas;

use App\Models\ValueProposition;
use App\Support\MediaUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ValuePropositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Konten')
                    ->columns(2)
                    ->schema([
                        Select::make('group')
                            ->label('Bagian landing page')
                            ->options([
                                ValueProposition::GROUP_BENEFIT => 'Manfaat',
                                ValueProposition::GROUP_PROCESS => 'Proses',
                                ValueProposition::GROUP_DELIVERABLE => 'Deliverables',
                            ])
                            ->required(),
                        TextInput::make('label.id')
                            ->label('Teks (Indonesia)')
                            ->required()
                            ->formatStateUsing(fn ($state, ?ValueProposition $record) => $record?->getTranslation('label', 'id') ?? ''),
                        TextInput::make('label.en')
                            ->label('Text (English)')
                            ->formatStateUsing(fn ($state, ?ValueProposition $record) => $record?->getTranslation('label', 'en') ?? ''),
                        MediaUpload::field('icon_path', 'value-proposition-icons', ['image/jpeg', 'image/png', 'image/webp'], 2048, true),
                        TextInput::make('sort_order')->numeric()->default(0)->required(),
                        Toggle::make('is_active')->default(true),
                    ]),
            ]);
    }
}
