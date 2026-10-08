<?php

namespace Tests\Feature\Events;

use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\Events\RegistrationConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_event_list_renders_in_both_locales(): void
    {
        Event::factory()->create();

        $this->get('/acara')->assertOk()->assertSee('lang="id"', false);
        $this->get('/en/acara')->assertOk()->assertSee('lang="en"', false);
    }

    public function test_the_list_shows_only_published_upcoming_events(): void
    {
        $visible = Event::factory()->create();
        $unpublished = Event::factory()->unpublished()->create();
        $past = Event::factory()->past()->create();

        $response = $this->get('/acara');

        $response->assertSee($visible->title);
        $response->assertDontSee($unpublished->title);
        $response->assertDontSee($past->title);
    }

    public function test_paid_events_remain_visible_but_cannot_be_enrolled_until_p5b(): void
    {
        $event = Event::factory()->paid()->create();
        $customer = Customer::factory()->create();

        $this->get('/acara')
            ->assertOk()
            ->assertSee(__('events.paid'));

        $this->get('/acara/'.$event->slug)
            ->assertOk()
            ->assertSee(__('events.paid_unavailable'))
            ->assertDontSee(__('events.register'));

        $this->actingAs($customer, 'customer')
            ->post('/acara/'.$event->slug.'/daftar')
            ->assertRedirect('/acara/'.$event->slug)
            ->assertSessionHas('error', __('events.error_paid_unavailable'));

        $this->assertSame(0, EventRegistration::count());
        $this->assertSame(0, $event->fresh()->seats_taken);
    }

    public function test_paid_event_unavailable_message_is_translated_on_english_routes(): void
    {
        $event = Event::factory()->paid()->create();

        $this->get('/en/acara')
            ->assertOk()
            ->assertSee(__('events.paid', [], 'en'));

        $this->get('/en/acara/'.$event->slug)
            ->assertOk()
            ->assertSee(__('events.paid_unavailable', [], 'en'))
            ->assertDontSee(__('events.register', [], 'en'));
    }

    public function test_event_list_cache_excludes_seat_availability_and_reads_it_live(): void
    {
        $event = Event::factory()->capacity(2)->create();

        $this->get('/acara')->assertSee('Sisa 2 kursi');

        $cachedEvents = Cache::get(Event::listingCacheKey('id'));

        $this->assertIsArray($cachedEvents);
        $this->assertArrayNotHasKey('capacity', $cachedEvents[0]);
        $this->assertArrayNotHasKey('seats_taken', $cachedEvents[0]);

        Event::query()->whereKey($event->getKey())->update(['seats_taken' => 2]);

        $this->get('/acara')->assertSee('Kuota penuh');
    }

    public function test_saving_an_event_invalidates_the_cached_public_listing(): void
    {
        $event = Event::factory()->create();
        $idCacheKey = Event::listingCacheKey('id');
        $enCacheKey = Event::listingCacheKey('en');

        $this->get('/acara')->assertOk();
        $this->get('/en/acara')->assertOk();
        $this->assertTrue(Cache::has($idCacheKey));
        $this->assertTrue(Cache::has($enCacheKey));

        $event->setTranslation('title', 'id', 'Judul Diperbarui');
        $event->save();

        $this->assertFalse(Cache::has($idCacheKey));
        $this->assertFalse(Cache::has($enCacheKey));
        $this->get('/acara')->assertSee('Judul Diperbarui');
    }

    public function test_deleting_and_restoring_an_event_invalidates_both_locale_listings(): void
    {
        $event = Event::factory()->create();
        $idCacheKey = Event::listingCacheKey('id');
        $enCacheKey = Event::listingCacheKey('en');

        $this->get('/acara')->assertOk();
        $this->get('/en/acara')->assertOk();

        $event->delete();

        $this->assertFalse(Cache::has($idCacheKey));
        $this->assertFalse(Cache::has($enCacheKey));

        $this->get('/acara')->assertOk();
        $this->get('/en/acara')->assertOk();
        $event->restore();

        $this->assertFalse(Cache::has($idCacheKey));
        $this->assertFalse(Cache::has($enCacheKey));
    }

    public function test_event_listing_query_count_does_not_grow_with_the_number_of_events(): void
    {
        Event::factory()->count(15)->create();
        Cache::forget(Event::listingCacheKey('id'));

        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        try {
            $this->get('/acara')->assertOk();
            $cacheMissQueries = $connection->getQueryLog();

            $connection->flushQueryLog();
            $this->get('/acara')->assertOk();
            $cacheHitQueries = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }

        $eventQueryCount = fn (array $queries): int => collect($queries)
            ->filter(fn (array $query): bool => preg_match('/\bfrom\s+[`"]?events\b/i', $query['query']) === 1)
            ->count();

        $this->assertSame(2, $eventQueryCount($cacheMissQueries), 'A cache miss should query the catalog and live availability.');
        $this->assertSame(1, $eventQueryCount($cacheHitQueries), 'A cache hit should query live availability only.');
    }

    public function test_an_unpublished_event_detail_page_is_not_found(): void
    {
        $event = Event::factory()->unpublished()->create();

        $this->get('/acara/'.$event->slug)->assertNotFound();
    }

    public function test_an_unpublished_event_cannot_be_enrolled_in_directly(): void
    {
        $event = Event::factory()->unpublished()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post('/acara/'.$event->slug.'/daftar')
            ->assertNotFound();

        $this->assertSame(0, EventRegistration::count());
        $this->assertSame(0, $event->fresh()->seats_taken);
    }

    public function test_a_verified_customer_can_enroll_in_a_free_event(): void
    {
        Notification::fake();

        $event = Event::factory()->free()->capacity(10)->create();
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->post('/acara/'.$event->slug.'/daftar')
            ->assertRedirect('/acara/'.$event->slug);

        $registration = EventRegistration::sole();

        $this->assertSame(EventRegistration::STATUS_CONFIRMED, $registration->status);
        $this->assertNotNull($registration->confirmed_at);
        $this->assertSame(1, $event->fresh()->seats_taken);
    }

    public function test_enrolling_snapshots_the_attendee_identity(): void
    {
        Notification::fake();

        // So the record stays readable after the account is deleted, and a
        // later name change does not rewrite who attended.
        $event = Event::factory()->create();
        $customer = Customer::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone' => '081234567890',
        ]);

        $this->actingAs($customer, 'customer')->post('/acara/'.$event->slug.'/daftar');

        $registration = EventRegistration::sole();

        $this->assertSame('Budi Santoso', $registration->attendee_name);
        $this->assertSame('budi@example.test', $registration->attendee_email);
        $this->assertSame('081234567890', $registration->attendee_phone);
    }

    public function test_the_confirmation_notification_is_queued_and_sent(): void
    {
        Notification::fake();

        $event = Event::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->post('/acara/'.$event->slug.'/daftar');

        Notification::assertSentTo($customer, RegistrationConfirmed::class);

        // At launch ~500 people enroll within minutes; a synchronous send would
        // hold a PHP worker each, waiting on an external mail API.
        $this->assertInstanceOf(
            ShouldQueue::class,
            new RegistrationConfirmed(EventRegistration::sole()),
        );
    }

    public function test_a_ticket_code_is_issued_and_is_not_derived_from_the_id(): void
    {
        Notification::fake();

        $event = Event::factory()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post('/acara/'.$event->slug.'/daftar');

        $registration = EventRegistration::sole();

        $this->assertNotEmpty($registration->ticket_code);
        // A derived code would leak how many people have registered.
        $this->assertStringNotContainsString((string) $registration->id, $registration->ticket_code);
    }

    public function test_an_unverified_customer_cannot_enroll(): void
    {
        $event = Event::factory()->create();
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->post('/acara/'.$event->slug.'/daftar')
            ->assertRedirect('/verifikasi-email');

        $this->assertSame(0, EventRegistration::count());
        $this->assertSame(0, $event->fresh()->seats_taken);
    }

    public function test_a_guest_cannot_enroll(): void
    {
        $event = Event::factory()->create();

        $this->post('/acara/'.$event->slug.'/daftar')->assertRedirect('/masuk');

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_a_duplicate_enrollment_is_rejected_and_does_not_take_a_second_seat(): void
    {
        Notification::fake();

        $event = Event::factory()->capacity(10)->create();
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->post('/acara/'.$event->slug.'/daftar');
        $this->actingAs($customer, 'customer')->post('/acara/'.$event->slug.'/daftar');

        $this->assertSame(1, EventRegistration::count());
        $this->assertSame(1, $event->fresh()->seats_taken);
    }

    public function test_a_full_event_rejects_enrollment(): void
    {
        $event = Event::factory()->capacity(1)->full()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post('/acara/'.$event->slug.'/daftar')
            ->assertSessionHas('error', __('events.error_full'));

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_an_event_with_unlimited_capacity_never_rejects(): void
    {
        Notification::fake();

        $event = Event::factory()->unlimited()->create();

        foreach (range(1, 5) as $i) {
            $this->actingAs(Customer::factory()->create(), 'customer')
                ->post('/acara/'.$event->slug.'/daftar');
        }

        $this->assertSame(5, EventRegistration::count());
        $this->assertNull($event->fresh()->capacity);
    }

    public function test_enrollment_outside_the_registration_window_is_rejected(): void
    {
        $event = Event::factory()->registrationClosed()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post('/acara/'.$event->slug.'/daftar')
            ->assertSessionHas('error', __('events.error_closed'));

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_enrollment_in_a_past_event_is_rejected(): void
    {
        $event = Event::factory()->past()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post('/acara/'.$event->slug.'/daftar')
            ->assertSessionHas('error', __('events.error_closed'));

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_enrolling_requires_a_post(): void
    {
        $event = Event::factory()->create();

        // Claiming a seat over GET could be triggered by a prefetch or an
        // <img src> on any page (guide.md 10.4).
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get('/acara/'.$event->slug.'/daftar')
            ->assertStatus(405);
    }
}
