<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminInitializationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'super-secret-password';

    public function test_it_creates_an_active_verified_superadmin(): void
    {
        $this->artisan('app:init-admin', [
            '--name' => 'Pemilik',
            '--username' => 'pemilik',
            '--email' => 'owner@example.test',
            '--password' => self::PASSWORD,
        ])->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.test')->sole();

        $this->assertSame(User::ROLE_SUPERADMIN, $user->role);
        $this->assertSame('pemilik', $user->username);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
    }

    public function test_it_is_idempotent_and_does_not_overwrite_the_password(): void
    {
        $this->artisan('app:init-admin', [
            '--name' => 'Pemilik',
            '--email' => 'owner@example.test',
            '--password' => self::PASSWORD,
        ])->assertSuccessful();

        $originalHash = User::query()->where('email', 'owner@example.test')->sole()->password;

        $this->artisan('app:init-admin', [
            '--email' => 'owner@example.test',
            '--password' => 'different-password-123',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.test')->sole();

        $this->assertSame(1, User::query()->where('role', User::ROLE_SUPERADMIN)->count());
        $this->assertSame($originalHash, $user->password);
        $this->assertSame('Pemilik', $user->name);
    }

    public function test_it_requires_force_before_promoting_an_existing_staff_account(): void
    {
        $editor = User::factory()->editor()->create(['email' => 'editor@example.test']);

        $this->artisan('app:init-admin', [
            '--name' => $editor->name,
            '--email' => $editor->email,
            '--password' => self::PASSWORD,
        ])->assertFailed();

        $this->assertSame(User::ROLE_EDITOR, $editor->fresh()->role);
    }

    public function test_force_promotes_an_existing_staff_account(): void
    {
        $editor = User::factory()->editor()->create(['email' => 'editor@example.test']);

        $this->artisan('app:init-admin', [
            '--name' => 'Pemilik',
            '--email' => $editor->email,
            '--password' => self::PASSWORD,
            '--force' => true,
        ])->assertSuccessful();

        $editor->refresh();

        $this->assertSame(User::ROLE_SUPERADMIN, $editor->role);
        $this->assertTrue($editor->is_active);
        $this->assertTrue(Hash::check(self::PASSWORD, $editor->password));
    }

    public function test_force_updates_an_existing_superadmin_password(): void
    {
        $superadmin = User::factory()->create([
            'email' => 'owner@example.test',
            'role' => User::ROLE_SUPERADMIN,
            'is_active' => false,
        ]);

        $this->artisan('app:init-admin', [
            '--name' => 'Pemilik',
            '--email' => $superadmin->email,
            '--password' => 'brand-new-password-123',
            '--force' => true,
        ])->assertSuccessful();

        $superadmin->refresh();

        $this->assertTrue($superadmin->is_active);
        $this->assertNotNull($superadmin->email_verified_at);
        $this->assertTrue(Hash::check('brand-new-password-123', $superadmin->password));
    }

    public function test_it_rejects_a_password_shorter_than_twelve_characters(): void
    {
        $this->artisan('app:init-admin', [
            '--name' => 'Pemilik',
            '--email' => 'owner@example.test',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.test']);
    }
}
