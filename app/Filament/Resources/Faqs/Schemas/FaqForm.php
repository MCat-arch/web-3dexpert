<?php

namespace App\Filament\Resources\Faqs\Schemas;

use App\Models\Faq;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pertanyaan dan jawaban')
                    ->columns(2)
                    ->schema([
                        TextInput::make('question.id')
                            ->label('Pertanyaan (Indonesia)')
                            ->required()
                            ->formatStateUsing(fn ($state, ?Faq $record) => $record?->getTranslation('question', 'id') ?? ''),
                        TextInput::make('question.en')
                            ->label('Question (English)')
                            ->formatStateUsing(fn ($state, ?Faq $record) => $record?->getTranslation('question', 'en') ?? ''),
                        Textarea::make('answer.id')
                            ->label('Jawaban (Indonesia)')
                            ->required()
                            ->rows(4)
                            ->formatStateUsing(fn ($state, ?Faq $record) => $record?->getTranslation('answer', 'id') ?? ''),
                        Textarea::make('answer.en')
                            ->label('Answer (English)')
                            ->rows(4)
                            ->formatStateUsing(fn ($state, ?Faq $record) => $record?->getTranslation('answer', 'en') ?? ''),
                        TextInput::make('sort_order')->numeric()->default(0)->required(),
                        \Filament\Forms\Components\Toggle::make('is_published')->default(true),
                    ]),
            ]);
    }
}
