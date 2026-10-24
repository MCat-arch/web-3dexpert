<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\EventRegistrations\EventRegistrationResource;
use App\Filament\Resources\EventRegistrations\Pages\ListEventRegistrations;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                'title' => ['id' => 'Acara Uji'],
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

    public function test_a_new_event_is_visible_on_the_public_list_immediately(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'title' => ['id' => 'Acara Publik Baru'],
                'starts_at' => now()->addWeek(),
                'location_type' => Event::LOCATION_OFFLINE,
                'capacity' => 10,
                'price' => 0,
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->get('/acara')
            ->assertOk()
            ->assertSee('Acara Publik Baru');
    }

    public function test_a_new_event_is_published_by_default(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'title' => ['id' => 'Acara Langsung'],
                'starts_at' => now()->addWeek(),
                'location_type' => Event::LOCATION_OFFLINE,
                'capacity' => 10,
                'price' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('events', [
            'slug' => 'acara-langsung',
            'is_published' => true,
        ]);
    }

    public function test_a_blank_price_is_saved_as_free(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'title' => ['id' => 'Acara Gratis'],
                'starts_at' => now()->addWeek(),
                'location_type' => Event::LOCATION_OFFLINE,
                'capacity' => 10,
                'price' => '',
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('events', [
            'slug' => 'acara-gratis',
            'price' => 0,
        ]);
    }

    public function test_an_event_can_be_created_with_a_poster_and_a_blank_price(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'title' => ['id' => 'Acara Poster'],
                'cover_path' => UploadedFile::fake()->image('poster.jpg', 80, 80),
                'starts_at' => now()->addWeek(),
                'location_type' => Event::LOCATION_OFFLINE,
                'capacity' => 10,
                'price' => '',
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('slug', 'acara-poster')->firstOrFail();

        $this->assertNotNull($event->cover_path);
        $this->assertDatabaseHas('media_assets', ['path' => $event->cover_path]);
    }

    public function test_the_end_time_must_be_after_the_start_time(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'title' => ['id' => 'Acara Salah Waktu'],
                'starts_at' => now()->addWeek(),
                'ends_at' => now()->addWeek()->subHour(),
                'location_type' => Event::LOCATION_OFFLINE,
                'capacity' => 10,
                'price' => 0,
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['ends_at']);

        $this->assertDatabaseMissing('events', [
            'slug' => 'acara-salah-waktu',
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

    public function test_registration_list_groups_by_event_by_default(): void
    {
        EventRegistration::factory()
            ->for(Event::factory()->create())
            ->for(Customer::factory()->create())
            ->create();

        $this->actingAs($this->staff());

        Livewire::test(ListEventRegistrations::class)
            ->assertSet('tableGrouping', 'event_id:asc');
    }

    public function test_event_edit_page_shows_registrations_for_that_event_only(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();

        $registration = EventRegistration::factory()
            ->for($event)
            ->for(Customer::factory()->create())
            ->create(['attendee_name' => 'Peserta Acara Ini']);

        $otherRegistration = EventRegistration::factory()
            ->for($otherEvent)
            ->for(Customer::factory()->create())
            ->create(['attendee_name' => 'Peserta Acara Lain']);

        $this->actingAs($this->staff());

        $this->get(EventResource::getUrl('edit', ['record' => $event]))
            ->assertOk()
            // Filament lazy-loads relation managers, so the initial HTML
            // contains the placeholder rather than the table heading.
            ->assertSee(RegistrationsRelationManager::class);

        Livewire::test(RegistrationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditEvent::class,
        ])
            ->assertSee('Pendaftaran Peserta')
            ->assertCanSeeTableRecords([$registration])
            ->assertCanNotSeeTableRecords([$otherRegistration]);
    }

    public function test_the_per_event_registration_table_keeps_the_shared_actions(): void
    {
        $event = Event::factory()->create();
        $registration = EventRegistration::factory()
            ->for($event)
            ->for(Customer::factory()->create())
            ->create();

        $this->actingAs($this->staff());

        Livewire::test(RegistrationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditEvent::class,
        ])
            ->callTableAction('markAttended', $registration)
            ->assertHasNoTableActionErrors();

        $this->assertSame(EventRegistration::STATUS_ATTENDED, $registration->fresh()->status);
    }

    public function test_the_per_event_registration_table_requires_registration_access(): void
    {
        $event = Event::factory()->create();
        $inactiveAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => false,
        ]);

        $this->actingAs($inactiveAdmin);

        $this->assertFalse(
            RegistrationsRelationManager::canViewForRecord($event, EditEvent::class),
        );
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
