<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\JobVacancies\JobVacancyResource;
use App\Filament\Resources\JobVacancies\Pages\CreateJobVacancy;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VacancyAdminTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_list_page_has_an_explicit_create_button(): void
    {
        $this->actingAs($this->staff())
            ->get(JobVacancyResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Tambah lowongan');
    }

    public function test_the_create_page_renders_without_legacy_english_inputs(): void
    {
        $this->actingAs($this->staff())
            ->get(JobVacancyResource::getUrl('create'))
            ->assertOk()
            ->assertDontSee('Judul (Inggris)')
            ->assertDontSee('Persyaratan (Inggris)');
    }

    public function test_admin_can_create_a_published_vacancy_with_indonesian_fields_only(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CreateJobVacancy::class)
            ->fillForm([
                'title' => ['id' => 'Desainer 3D'],
                'slug' => '',
                'requirement' => ['id' => 'Menguasai Blender dan SketchUp.'],
                'description' => ['id' => 'Mengerjakan visualisasi arsitektur.'],
                'department' => 'Desain',
                'location' => 'Jakarta',
                'employment_type' => 'Full Time',
                'closes_at' => now()->addWeek(),
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $vacancy = JobVacancy::query()->where('slug', 'desainer-3d')->sole();

        $this->assertTrue($vacancy->is_published);
        $this->assertSame('Desainer 3D', $vacancy->getTranslation('title', 'id'));
        $this->assertSame('Menguasai Blender dan SketchUp.', $vacancy->getTranslation('requirement', 'id'));
    }

    public function test_a_published_vacancy_appears_on_the_public_careers_page(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CreateJobVacancy::class)
            ->fillForm([
                'title' => ['id' => 'Drafter Arsitektur'],
                'slug' => '',
                'requirement' => ['id' => 'Teliti dan komunikatif.'],
                'description' => ['id' => 'Membantu tim gambar kerja.'],
                'department' => 'Desain',
                'location' => 'Bandung',
                'employment_type' => 'Contract',
                'closes_at' => now()->addWeek(),
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->get('/karir')
            ->assertOk()
            ->assertSee('Drafter Arsitektur');
    }

    public function test_a_draft_vacancy_does_not_appear_on_the_public_page(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CreateJobVacancy::class)
            ->fillForm([
                'title' => ['id' => 'Lowongan Draf'],
                'slug' => '',
                'requirement' => ['id' => 'Kualifikasi rahasia.'],
                'description' => ['id' => 'Belum boleh tampil.'],
                'department' => 'Desain',
                'location' => 'Remote',
                'employment_type' => 'Internship',
                'is_published' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->get('/karir')
            ->assertOk()
            ->assertDontSee('Lowongan Draf');
    }
}
