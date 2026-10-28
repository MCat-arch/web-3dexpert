<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
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

    public function test_the_superadmin_sees_users_traffic_and_performance_widgets(): void
    {
        Customer::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        Customer::factory()->create(['is_active' => true, 'email_verified_at' => null]);

        $response = $this->actingAs($this->staff(User::ROLE_SUPERADMIN))->get('/admin');

        $response->assertOk()
            ->assertSee('Pelanggan aktif')
            ->assertSee('Pelanggan aktif terverifikasi')
            ->assertSee('Pelanggan aktif belum verifikasi')
            ->assertSee('Staf belum verifikasi')
            ->assertSee('Kunjungan hari ini')
            ->assertSee('Pengunjung unik hari ini')
            ->assertSee('Total enrollment')
            ->assertSee('Traffic 30 hari');
    }

    public function test_other_staff_roles_do_not_see_the_superadmin_widgets(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_LMS_ADMIN, User::ROLE_EDITOR] as $role) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();

            $response = $this->actingAs($this->staff($role))->get('/admin');

            $response->assertOk()
                ->assertDontSee('Pelanggan aktif terverifikasi')
                ->assertDontSee('Kunjungan hari ini')
                ->assertDontSee('Traffic 30 hari');
        }
    }
}
