<?php

namespace Tests\Feature\Admin;

use App\Models\AssignmentSubmission;
use App\Models\CourseEnrollment;
use App\Models\Event;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_the_dashboard_renders_for_every_staff_role(): void
    {
        foreach (User::roles() as $role) {
            // Switching users inside one test leaves AuthenticateSession's
            // password hash from the previous user in the session, which logs
            // the next request out. Start each role with a clean session.
            $this->flushSession();
            $this->app['auth']->forgetGuards();

            $response = $this->actingAs($this->staff($role))->get('/admin');

            $this->assertSame(
                200,
                $response->status(),
                $role.' was redirected to '.$response->headers->get('Location'),
            );
        }
    }

    public function test_an_editor_sees_marketing_stats_but_not_revenue_or_hiring(): void
    {
        $response = $this->actingAs($this->staff(User::ROLE_EDITOR))->get('/admin');

        $response->assertOk()
            ->assertSee('Acara terbit')
            ->assertDontSee('Pendapatan bulan ini')
            ->assertDontSee('Pelamar baru')
            ->assertDontSee('Tugas menunggu');
    }

    public function test_lms_admin_sees_progress_and_submissions_but_not_revenue(): void
    {
        $response = $this->actingAs($this->staff(User::ROLE_LMS_ADMIN))->get('/admin');

        $response->assertOk()
            ->assertSee('Siswa aktif')
            ->assertSee('Tugas menunggu')
            ->assertDontSee('Pendapatan bulan ini')
            ->assertDontSee('Acara terbit')
            ->assertDontSee('Pelamar baru');
    }

    public function test_admin_sees_the_commercial_and_hiring_widgets(): void
    {
        Event::factory()->create(['is_published' => true]);
        JobVacancy::factory()->create(['is_published' => true, 'closes_at' => null]);
        JobApplication::factory()->create(['status' => JobApplication::STATUS_SUBMITTED]);

        $enrollment = CourseEnrollment::factory()->create();
        Payment::factory()->settled()->create([
            'payable_type' => CourseEnrollment::class,
            'payable_id' => $enrollment->id,
            'paid_at' => now(),
        ]);
        AssignmentSubmission::factory()->create();

        $response = $this->actingAs($this->staff(User::ROLE_ADMIN))->get('/admin');

        $response->assertOk()
            ->assertSee('Acara terbit')
            ->assertSee('Lowongan terbuka')
            ->assertSee('Pelamar baru')
            ->assertSee('Pendapatan bulan ini')
            ->assertSee('Tugas menunggu');
    }
}
