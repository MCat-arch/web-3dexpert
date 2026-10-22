<?php

namespace App\Filament\Resources\CourseSections\Pages;

use App\Filament\Resources\CourseSections\CourseSectionResource;
use Filament\Resources\Pages\ListRecords;

class ListCourseSections extends ListRecords
{
    protected static string $resource = CourseSectionResource::class;

    public function getTitle(): string
    {
        return 'Kurikulum';
    }

    public function getSubheading(): ?string
    {
        return 'Cari bab dari semua kursus, lalu buka Kelola materi untuk menambah atau mengubah isi materi.';
    }
}
