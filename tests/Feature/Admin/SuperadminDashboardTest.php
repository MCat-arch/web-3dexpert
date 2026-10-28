<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_the_superadmin_sees_only_the_summary_grid_and_traffic_chart(): void
    {
        Customer::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        Customer::factory()->create(['is_active' => true, 'email_verified_at' => null]);

        $response = $this->actingAs($this->staff(User::ROLE_SUPERADMIN))->get('/admin');

        $response->assertOk();

        foreach ([
            'Pelanggan aktif',
            'Pelanggan aktif terverifikasi',
            'Pelanggan aktif belum verifikasi',
            'Total siswa',
            'Total pendaftar acara',
            'Total pendaftar lowongan',
            'Kunjungan hari ini',
            'Kunjungan 7 hari terakhir',
            'Pengunjung unik',
            'Rata-rata respons',
            'Error server',
            'Total acara',
            'Total enrolment',
            'Total acara terbit',
            'Acara mendatang',
            'Lowongan terbuka',
            'Total pendapatan',
            'Traffic 30 hari',
        ] as $label) {
            $response->assertSee($label);
        }

        // The stacked leftovers are gone for this role.
        $response->assertDontSee('Pendaftaran terbaru')
            ->assertDontSee('Lamaran terbaru')
            ->assertDontSee('Tugas menunggu penilaian')
            ->assertDontSee('Selamat Datang');
    }

    public function test_the_summary_grid_counts_all_records(): void
    {
        Customer::factory()->count(3)->create(['is_active' => true, 'email_verified_at' => now()]);
        Customer::factory()->create(['is_active' => true, 'email_verified_at' => null]);

        $course = Course::factory()->create();
        CourseEnrollment::factory()->count(2)->create([
            'course_id' => $course->id,
            'status' => CourseEnrollment::STATUS_ACTIVE,
        ]);
        CourseEnrollment::factory()->create([
            'course_id' => $course->id,
            'status' => CourseEnrollment::STATUS_REVOKED,
        ]);

        $event = Event::factory()->create(['is_published' => true, 'starts_at' => now()->addWeek()]);
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);

        $vacancy = JobVacancy::factory()->create(['is_published' => true, 'closes_at' => now()->addWeek()]);
        JobApplication::factory()->create(['job_vacancy_id' => $vacancy->id]);

        Payment::factory()->settled()->create(['gross_amount' => 1_500_000]);

        $response = $this->actingAs($this->staff(User::ROLE_SUPERADMIN))->get('/admin');

        $response->assertOk()
            ->assertSee('Rp 1.500.000');
    }

    public function test_other_staff_roles_keep_their_own_dashboard(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_LMS_ADMIN, User::ROLE_EDITOR] as $role) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();

            $response = $this->actingAs($this->staff($role))->get('/admin');

            $response->assertOk()
                ->assertDontSee('Total pendapatan')
                ->assertDontSee('Kunjungan hari ini')
                ->assertDontSee('Traffic 30 hari');
        }
    }
}
