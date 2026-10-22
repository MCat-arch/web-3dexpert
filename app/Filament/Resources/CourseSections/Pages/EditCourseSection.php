<?php

namespace App\Filament\Resources\CourseSections\Pages;

use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\CourseSections\CourseSectionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditCourseSection extends EditRecord
{
    protected static string $resource = CourseSectionResource::class;

    public function getTitle(): string
    {
        $title = $this->getRecord()->getTranslation('title', 'id');

        return 'Kelola materi: '.$title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToCourse')
                ->label('Kembali ke kursus')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => CourseResource::getUrl('edit', [
                    'record' => $this->getRecord()->course_id,
                ])),
        ];
    }
}
