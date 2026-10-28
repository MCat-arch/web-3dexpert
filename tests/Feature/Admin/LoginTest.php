<?php

namespace Tests\Feature\Admin;

use App\Filament\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_SUPERADMIN,
            'is_active' => true,
            'email' => 'boss@example.com',
            'username' => 'boss',
            'password' => Hash::make('a-very-secret-password'),
        ], $attributes));
    }

    /**
     * Regression: Filament stores the failure on `data.email`, but this panel's
     * only field is `data.login`. The error was therefore invisible and a
     * rejected login looked like a page that simply did nothing.
     */
    public function test_invalid_credentials_are_shown_on_the_login_field(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['login' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);
    }

    public function test_invalid_credentials_render_a_visible_message(): void
    {
        $component = Livewire::test(Login::class)
            ->fillForm(['login' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->call('authenticate');

        $component->assertSee(__('filament-panels::auth/pages/login.messages.failed'));
    }

    public function test_a_staff_member_can_log_in_with_their_email(): void
    {
        $user = $this->staff();

        Livewire::test(Login::class)
            ->fillForm(['login' => 'boss@example.com', 'password' => 'a-very-secret-password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_staff_member_can_log_in_with_their_username(): void
    {
        $user = $this->staff();

        Livewire::test(Login::class)
            ->fillForm(['login' => 'boss', 'password' => 'a-very-secret-password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }
}
