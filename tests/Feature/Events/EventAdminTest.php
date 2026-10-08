<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\EventRegistrations\EventRegistrationResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Smoke tests for the staff side of P5a.
 *
 * Without these, the first evidence that a Filament API was used incorrectly
 * would be a 500 in the admin panel -- and P5a is not usable at all unless
 * staff can create an event.
 */
class EventAdminTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    public function test_staff_can_open_the_event_list(): void
    {
        $this->actingAs($this->staff())
            ->get(EventResource::getUrl('index'))
            ->assertOk();
    }

    public function test_staff_can_open_the_event_create_form(): void
    {
        $this->actingAs($this->staff())
            ->get(EventResource::getUrl('create'))
            ->assertOk();
    }

    public function test_staff_can_create_an_event(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'title' => ['id' => 'Acara Uji', 'en' => 'Test Event'],
                'starts_at' => now()->addWeek(),
                'location_type' => Event::LOCATION_OFFLINE,
                'capacity' => 25,
                'price' => 0,
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('events', [
            'slug' => 'acara-uji',
            'is_published' => true,
            'capacity' => 25,
            'seats_taken' => 0,
        ]);
    }

    public function test_staff_can_open_the_event_edit_form(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($this->staff())
            ->get(EventResource::getUrl('edit', ['record' => $event]))
            ->assertOk();
    }

    public function test_staff_can_open_the_registration_list(): void
    {
        EventRegistration::factory()
            ->for(Event::factory()->create())
            ->for(Customer::factory()->create())
            ->create();

        $this->actingAs($this->staff())
            ->get(EventRegistrationResource::getUrl('index'))
            ->assertOk();
    }

    public function test_registrations_cannot_be_created_by_staff(): void
    {
        // They come from the public enrollment flow, which claims a seat
        // atomically. A staff-created row would not increment seats_taken.
        $this->assertFalse(EventRegistrationResource::canCreate());
    }

    public function test_a_customer_cannot_reach_the_event_admin(): void
    {
        /*
         * A customer is authenticated on the `customer` guard, which makes
         * them a GUEST to the `web` guard the panel is pinned to -- so
         * Filament redirects them to its own login rather than returning 403.
         *
         * Asserting "not 200" rather than a specific code: the security
         * property is that the panel is unreachable, and pinning the exact
         * response would make this test fail on a cosmetic Filament change
         * while proving nothing extra.
         */
        $response = $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(EventResource::getUrl('index'));

        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_a_guest_cannot_reach_the_event_admin(): void
    {
        $this->get(EventResource::getUrl('index'))->assertRedirect();
    }
}
