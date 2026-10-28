<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\PaymentSettings;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentSettingsTest extends TestCase
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

    public function test_the_page_renders_for_staff_with_the_payments_area(): void
    {
        $this->actingAs($this->staff(User::ROLE_SUPERADMIN))
            ->get('/admin/payment-settings')
            ->assertOk()
            ->assertSee('Nama bank')
            ->assertSee('Nomor rekening')
            ->assertSee('Nama pemilik rekening');
    }

    public function test_staff_without_the_payments_area_cannot_open_it(): void
    {
        $this->actingAs($this->staff(User::ROLE_EDITOR))
            ->get('/admin/payment-settings')
            ->assertForbidden();
    }

    public function test_saving_stores_the_bank_details_and_audits_the_change(): void
    {
        $user = $this->staff(User::ROLE_SUPERADMIN);

        Livewire::actingAs($user)
            ->test(PaymentSettings::class)
            ->set('bankName', 'Bank Mandiri')
            ->set('bankAccount', '123-00-1234567-8')
            ->set('bankHolder', 'PT Luxora Aura Semesta')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Bank Mandiri', Settings::get('payment_bank_name'));
        $this->assertSame('123-00-1234567-8', Settings::get('payment_bank_account'));
        $this->assertSame('PT Luxora Aura Semesta', Settings::get('payment_bank_holder'));

        // Settings::set() creates the rows, which is what makes them visible
        // in the generic Site Settings screen as well.
        $this->assertDatabaseHas('site_settings', ['key' => 'payment_bank_name', 'group' => 'payments']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'payment.bank_settings_updated']);
    }

    public function test_empty_fields_are_rejected(): void
    {
        Livewire::actingAs($this->staff(User::ROLE_SUPERADMIN))
            ->test(PaymentSettings::class)
            ->set('bankName', '')
            ->set('bankAccount', '')
            ->set('bankHolder', '')
            ->call('save')
            ->assertHasErrors(['bankName', 'bankAccount', 'bankHolder']);

        $this->assertDatabaseMissing('site_settings', ['key' => 'payment_bank_name']);
    }
}
