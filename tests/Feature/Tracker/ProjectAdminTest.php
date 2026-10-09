<?php

namespace Tests\Feature\Tracker;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff side of P7. Manual entry is the ONLY way a project exists (A1), so if
 * these pages do not load the feature has no data and no reason to exist.
 */
class ProjectAdminTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    public function test_staff_can_open_the_project_list(): void
    {
        Project::factory()->count(3)->create();

        $this->actingAs($this->staff(), 'web')
            ->get(ProjectResource::getUrl('index'))
            ->assertOk();
    }

    public function test_staff_can_open_the_create_form(): void
    {
        $this->actingAs($this->staff(), 'web')
            ->get(ProjectResource::getUrl('create'))
            ->assertOk();
    }

    public function test_staff_can_open_the_edit_form_and_see_the_generated_code(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->staff(), 'web')
            ->get(ProjectResource::getUrl('edit', ['record' => $project]))
            ->assertOk()
            ->assertSee($project->project_code);
    }

    public function test_the_edit_page_renders_with_progress_updates(): void
    {
        // The relation manager is where staff actually work, so it has to
        // render with real rows rather than only on an empty project.
        $project = Project::factory()->create();
        ProjectUpdate::factory()->for($project)->count(3)->create();

        $this->actingAs($this->staff(), 'web')
            ->get(ProjectResource::getUrl('edit', ['record' => $project]))
            ->assertOk();
    }

    public function test_a_customer_cannot_reach_the_project_admin(): void
    {
        $response = $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(ProjectResource::getUrl('index'));

        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_a_guest_cannot_reach_the_project_admin(): void
    {
        $this->get(ProjectResource::getUrl('index'))->assertRedirect();
    }

    public function test_a_code_is_generated_on_create_without_staff_input(): void
    {
        // The form field is disabled and not dehydrated, so the model's
        // creating hook is the only source of a code.
        $project = Project::create([
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'title' => 'Desain Rumah',
        ]);

        $this->assertMatchesRegularExpression('/^3DX-[234678A-Z]{8}$/', $project->project_code);
    }
}
