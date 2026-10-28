<?php

namespace Tests\Feature\Seeders;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_verified_active_customers_and_no_panel_accounts(): void
    {
        $this->seed(CustomerSeeder::class);

        $this->assertSame(count(CustomerSeeder::CUSTOMERS), Customer::count());
        $this->assertSame(0, User::count(), 'Seeding must never create an admin panel account.');

        $customer = Customer::query()->where('email', 'indra.hardja@gmail.com')->firstOrFail();

        $this->assertSame('Indra Wibawa Hardja', $customer->name);
        $this->assertTrue($customer->is_active);
        $this->assertTrue($customer->hasVerifiedEmail());
        $this->assertTrue(Hash::check('password123', $customer->password));
    }

    public function test_running_twice_does_not_duplicate_anything(): void
    {
        $this->seed(CustomerSeeder::class);
        $this->seed(CustomerSeeder::class);

        $this->assertSame(count(CustomerSeeder::CUSTOMERS), Customer::withTrashed()->count());
    }

    public function test_it_never_overwrites_an_existing_account(): void
    {
        $existing = Customer::factory()->create([
            'email' => 'indra.hardja@gmail.com',
            'name' => 'Nama Yang Sudah Diubah',
            'password' => 'password-asli-yang-berbeda',
        ]);

        $this->seed(CustomerSeeder::class);

        $existing->refresh();

        $this->assertSame('Nama Yang Sudah Diubah', $existing->name);
        $this->assertTrue(Hash::check('password-asli-yang-berbeda', $existing->password));
        $this->assertSame(count(CustomerSeeder::CUSTOMERS), Customer::count());
    }

    public function test_a_soft_deleted_email_is_left_alone(): void
    {
        $deleted = Customer::factory()->create(['email' => 'indra.hardja@gmail.com']);
        $deleted->delete();

        $this->seed(CustomerSeeder::class);

        $this->assertSame(count(CustomerSeeder::CUSTOMERS) - 1, Customer::count());
        $this->assertTrue(Customer::withTrashed()->where('email', 'indra.hardja@gmail.com')->firstOrFail()->trashed());
    }
}
