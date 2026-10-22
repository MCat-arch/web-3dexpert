<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\RelationManagers\SectionsRelationManager;
use App\Filament\Resources\CourseSections\CourseSectionResource;
use App\Filament\Resources\CourseSections\Pages\EditCourseSection;
use App\Filament\Resources\CourseSections\RelationManagers\LessonsRelationManager;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseCurriculumAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_index_is_available_to_admin(): void
    {
        $section = CourseSection::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(CourseSectionResource::getUrl('index'))
            ->assertOk()
            ->assertSee($section->getTranslation('title', 'id'));
    }

    public function test_curriculum_index_is_available_to_lms_admin(): void
    {
        $section = CourseSection::factory()->create();

        $this->actingAs(User::factory()->lmsAdmin()->create())
            ->get(CourseSectionResource::getUrl('index'))
            ->assertOk()
            ->assertSee($section->getTranslation('title', 'id'));
    }

    public function test_editor_cannot_open_the_curriculum_index(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->get(CourseSectionResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_the_curriculum_index_links_to_lesson_management(): void
    {
        $section = CourseSection::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(CourseSectionResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Kelola materi')
            ->assertSee($section->course->getTranslation('title', 'id'));
    }

    public function test_the_course_edit_page_exposes_the_curriculum_section(): void
    {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create(['course_section_id' => $section->id]);

        $admin = User::factory()->admin()->create();
        $sectionTitle = $section->getTranslation('title', 'id');

        $this->actingAs($admin)
            ->get(CourseResource::getUrl('edit', ['record' => $course]))
            ->assertOk()
            ->assertSee('Kurikulum');

        Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])
            ->assertSuccessful()
            ->assertSee('Tambah bab')
            ->assertSee($sectionTitle);
    }

    public function test_the_lesson_management_page_exposes_material_crud(): void
    {
        $section = CourseSection::factory()->create([
            'title' => ['id' => 'Bab Materi'],
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(CourseSectionResource::getUrl('edit', ['record' => $section]))
            ->assertOk();

        Livewire::test(LessonsRelationManager::class, [
            'ownerRecord' => $section,
            'pageClass' => EditCourseSection::class,
        ])
            ->assertSuccessful()
            ->assertSee('Tambah materi');
    }
}
