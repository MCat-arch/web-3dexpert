<?php

namespace Tests\Feature\Events;

use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventRegistrationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_my_events_lists_only_the_signed_in_customers_registrations(): void
    {
        $mine = Customer::factory()->create();
        $theirs = Customer::factory()->create();

        $myEvent = Event::factory()->create(['title' => ['id' => 'Acara Saya', 'en' => 'My Event']]);
        $theirEvent = Event::factory()->create(['title' => ['id' => 'Acara Lain', 'en' => 'Other Event']]);

        EventRegistration::factory()->for($myEvent)->for($mine)->create();
        EventRegistration::factory()->for($theirEvent)->for($theirs)->create();

        $response = $this->actingAs($mine, 'customer')->get('/akun/acara');

        $response->assertOk()
            ->assertSee('Acara Saya')
            ->assertDontSee('Acara Lain');
    }

    public function test_my_events_requires_a_verified_customer(): void
    {
        $this->get('/akun/acara')->assertRedirect('/masuk');

        $this->actingAs(Customer::factory()->unverified()->create(), 'customer')
            ->get('/akun/acara')
            ->assertRedirect('/verifikasi-email');
    }

    public function test_a_customer_cannot_cancel_someone_elses_registration(): void
    {
        // Customers are authorised by ownership, not role (D1). Without the
        // policy, the registration id in the URL would be enough.
        $owner = Customer::factory()->create();
        $attacker = Customer::factory()->create();

        $registration = EventRegistration::factory()
            ->for(Event::factory()->capacity(10)->create())
            ->for($owner)
            ->create();

        $this->actingAs($attacker, 'customer')
            ->delete('/pendaftaran/'.$registration->id)
            ->assertForbidden();

        $this->assertFalse($registration->fresh()->isCancelled());
    }

    public function test_the_owner_can_cancel_their_own_registration(): void
    {
        $owner = Customer::factory()->create();

        $registration = EventRegistration::factory()
            ->for(Event::factory()->capacity(10)->create())
            ->for($owner)
            ->create();

        $this->actingAs($owner, 'customer')
            ->delete('/pendaftaran/'.$registration->id)
            ->assertRedirect();

        $this->assertTrue($registration->fresh()->isCancelled());
    }

    public function test_a_registration_cannot_be_cancelled_after_the_event_has_started(): void
    {
        // Cancelling then would return a seat that was already consumed.
        $owner = Customer::factory()->create();

        $registration = EventRegistration::factory()
            ->for(Event::factory()->past()->create())
            ->for($owner)
            ->create();

        $this->actingAs($owner, 'customer')
            ->delete('/pendaftaran/'.$registration->id)
            ->assertForbidden();

        $this->assertFalse($registration->fresh()->isCancelled());
    }

    public function test_cancelling_requires_a_delete_not_a_get(): void
    {
        $owner = Customer::factory()->create();

        $registration = EventRegistration::factory()
            ->for(Event::factory()->create())
            ->for($owner)
            ->create();

        $this->actingAs($owner, 'customer')
            ->get('/pendaftaran/'.$registration->id)
            ->assertStatus(405);
    }

    public function test_the_detail_page_shows_the_ticket_code_only_to_the_owner(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $event = Event::factory()->create();

        $registration = EventRegistration::factory()->for($event)->for($owner)->create();

        $this->actingAs($owner, 'customer')
            ->get('/acara/'.$event->slug)
            ->assertSee($registration->ticket_code);

        $this->actingAs($other, 'customer')
            ->get('/acara/'.$event->slug)
            ->assertDontSee($registration->ticket_code);
    }

    public function test_the_owner_sees_cancellation_controls_on_detail_and_my_events_pages(): void
    {
        $owner = Customer::factory()->create();
        $registration = EventRegistration::factory()
            ->for(Event::factory()->create())
            ->for($owner)
            ->create();

        $this->actingAs($owner, 'customer')
            ->get('/acara/'.$registration->event->slug)
            ->assertSee(__('events.cancel'));

        $this->actingAs($owner, 'customer')
            ->get('/akun/acara')
            ->assertSee(__('events.cancel'));
    }

    public function test_another_customer_does_not_see_cancellation_controls(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $registration = EventRegistration::factory()
            ->for(Event::factory()->create())
            ->for($owner)
            ->create();

        $this->actingAs($other, 'customer')
            ->get('/acara/'.$registration->event->slug)
            ->assertDontSee(__('events.cancel'));

        $this->actingAs($other, 'customer')
            ->get('/akun/acara')
            ->assertDontSee(__('events.cancel'));
    }
}
