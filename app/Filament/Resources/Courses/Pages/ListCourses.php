<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\CourseSections\CourseSectionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourses extends ListRecords
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('curriculum')
                ->label('Daftar kurikulum')
                ->icon('heroicon-o-book-open')
                ->url(CourseSectionResource::getUrl('index')),

            CreateAction::make(),
        ];
    }
}
