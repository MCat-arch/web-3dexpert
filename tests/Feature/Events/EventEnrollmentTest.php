<?php

namespace Tests\Feature\Events;

use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\Events\RegistrationConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function attendee(array $overrides = []): array
    {
        return array_merge([
            'attendee_name' => 'Budi Santoso',
            'attendee_phone' => '081234567890',
        ], $overrides);
    }

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

    public function test_an_ongoing_event_remains_visible_until_it_ends(): void
    {
        $ongoing = Event::factory()->create([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $this->get('/acara')
            ->assertOk()
            ->assertSee($ongoing->getTranslation('title', 'id'));
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
            ->post('/acara/'.$event->slug.'/daftar', $this->attendee())
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

    public function test_event_list_reads_seat_availability_live(): void
    {
        $event = Event::factory()->capacity(2)->create();

        $this->get('/acara')->assertSee('Sisa 2 kursi');

        Event::query()->whereKey($event->getKey())->update(['seats_taken' => 2]);

        $this->get('/acara')->assertSee('Kuota penuh');
    }

    public function test_editing_an_event_is_visible_immediately(): void
    {
        $event = Event::factory()->create();

        $this->get('/acara')->assertSee($event->getTranslation('title', 'id'));

        $event->setTranslation('title', 'id', 'Judul Diperbarui');
        $event->setTranslation('title', 'en', 'Judul Diperbarui');
        $event->save();

        $this->get('/acara')->assertSee('Judul Diperbarui');
        $this->get('/en/acara')->assertSee('Judul Diperbarui');
    }

    public function test_publishing_and_unpublishing_an_event_is_visible_immediately(): void
    {
        $event = Event::factory()->unpublished()->create();

        $this->get('/acara')->assertDontSee($event->getTranslation('title', 'id'));

        $event->update(['is_published' => true]);
        $this->get('/acara')->assertSee($event->getTranslation('title', 'id'));

        $event->update(['is_published' => false]);
        $this->get('/acara')->assertDontSee($event->getTranslation('title', 'id'));
    }

    public function test_deleting_and_restoring_an_event_is_visible_immediately(): void
    {
        $event = Event::factory()->create();

        $this->get('/acara')->assertSee($event->getTranslation('title', 'id'));
        $this->get('/en/acara')->assertSee($event->getTranslation('title', 'id'));

        $event->delete();

        $this->get('/acara')->assertDontSee($event->getTranslation('title', 'id'));
        $this->get('/en/acara')->assertDontSee($event->getTranslation('title', 'id'));

        $event->restore();

        $this->get('/acara')->assertSee($event->getTranslation('title', 'id'));
        $this->get('/en/acara')->assertSee($event->getTranslation('title', 'id'));
    }

    public function test_event_listing_query_count_does_not_grow_with_the_number_of_events(): void
    {
        Event::factory()->count(15)->create();

        $connection = DB::connection();
        $connection->enableQueryLog();

        try {
            $connection->flushQueryLog();
            $this->get('/acara')->assertOk();
            $firstRequestQueries = $connection->getQueryLog();

            $connection->flushQueryLog();
            $this->get('/acara')->assertOk();
            $secondRequestQueries = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }

        $eventQueryCount = fn (array $queries): int => collect($queries)
            ->filter(fn (array $query): bool => preg_match('/\bfrom\s+[`"]?events\b/i', $query['query']) === 1)
            ->count();

        $this->assertSame(2, $eventQueryCount($firstRequestQueries), 'One count query and one page query.');
        $this->assertSame(2, $eventQueryCount($secondRequestQueries), 'The query count is fixed, not dependent on stored data.');
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
            ->post('/acara/'.$event->slug.'/daftar', $this->attendee())
            ->assertRedirect('/acara/'.$event->slug);

        $registration = EventRegistration::sole();

        $this->assertSame(EventRegistration::STATUS_CONFIRMED, $registration->status);
        $this->assertNotNull($registration->confirmed_at);
        $this->assertSame(1, $event->fresh()->seats_taken);
    }

    public function test_enrolling_saves_the_attendee_identity_from_the_form(): void
    {
        Notification::fake();

        // The registration record stays readable after the account is deleted,
        // and a later name change does not rewrite who attended. The form is
        // pre-filled from the account but the attendee may correct it, so the
        // submitted values are what get stored.
        $event = Event::factory()->create();
        $customer = Customer::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone' => '081111111111',
        ]);

        $this->actingAs($customer, 'customer')->post('/acara/'.$event->slug.'/daftar', $this->attendee([
            'attendee_name' => 'Budi Santoso',
            'attendee_phone' => '081234567890',
        ]));

        $registration = EventRegistration::sole();

        $this->assertSame('Budi Santoso', $registration->attendee_name);
        $this->assertSame('budi@example.test', $registration->attendee_email);
        $this->assertSame('081234567890', $registration->attendee_phone);
        $this->assertSame(
            '081111111111',
            $customer->fresh()->phone,
            'Editing the event form must not change the account profile.',
        );
    }

    public function test_the_attendee_email_cannot_be_overridden_from_the_request(): void
    {
        Notification::fake();

        $event = Event::factory()->create();
        $customer = Customer::factory()->create(['email' => 'akun@example.test']);

        $this->actingAs($customer, 'customer')->post('/acara/'.$event->slug.'/daftar', $this->attendee([
            'attendee_email' => 'lain@example.test',
        ]));

        $this->assertSame('akun@example.test', EventRegistration::sole()->attendee_email);
    }

    public function test_attendee_name_and_phone_are_required(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post('/acara/'.$event->slug.'/daftar')
            ->assertSessionHasErrors(['attendee_name', 'attendee_phone']);

        $this->assertSame(0, EventRegistration::count());
        $this->assertSame(0, $event->fresh()->seats_taken);
    }

    public function test_an_invalid_phone_number_is_rejected(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post('/acara/'.$event->slug.'/daftar', $this->attendee(['attendee_phone' => 'bukan-nomor']))
            ->assertSessionHasErrors('attendee_phone');

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_the_registration_dialog_is_rendered_for_a_signed_in_customer(): void
    {
        $event = Event::factory()->create();
        $customer = Customer::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone' => '081234567890',
        ]);

        $response = $this->actingAs($customer, 'customer')->get('/acara/'.$event->slug);

        $response->assertOk()
            ->assertSee('data-event-register-dialog', false)
            ->assertSee('name="attendee_name"', false)
            ->assertSee('name="attendee_phone"', false)
            ->assertSee('value="budi@example.test"', false)
            ->assertSee('readonly', false)
            ->assertSee(__('events.confirm_register'))
            ->assertDontSee('name="attendee_email"', false);
    }

    public function test_the_success_message_is_visible_after_enrolling(): void
    {
        Notification::fake();

        $event = Event::factory()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->followingRedirects()
            ->post('/acara/'.$event->slug.'/daftar', $this->attendee())
            ->assertOk()
            ->assertSee('Pendaftaran berhasil');
    }

    public function test_the_confirmation_notification_is_queued_and_sent(): void
    {
        Notification::fake();

        $event = Event::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->post('/acara/'.$event->slug.'/daftar', $this->attendee());

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
            ->post('/acara/'.$event->slug.'/daftar', $this->attendee());

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

        $this->actingAs($customer, 'customer')->post('/acara/'.$event->slug.'/daftar', $this->attendee());
        $this->actingAs($customer, 'customer')->post('/acara/'.$event->slug.'/daftar', $this->attendee());

        $this->assertSame(1, EventRegistration::count());
        $this->assertSame(1, $event->fresh()->seats_taken);
    }

    public function test_a_full_event_rejects_enrollment(): void
    {
        $event = Event::factory()->capacity(1)->full()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post('/acara/'.$event->slug.'/daftar', $this->attendee())
            ->assertSessionHas('error', __('events.error_full'));

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_an_event_with_unlimited_capacity_never_rejects(): void
    {
        Notification::fake();

        $event = Event::factory()->unlimited()->create();

        foreach (range(1, 5) as $i) {
            $this->actingAs(Customer::factory()->create(), 'customer')
                ->post('/acara/'.$event->slug.'/daftar', $this->attendee());
        }

        $this->assertSame(5, EventRegistration::count());
        $this->assertNull($event->fresh()->capacity);
    }

    public function test_enrollment_outside_the_registration_window_is_rejected(): void
    {
        $event = Event::factory()->registrationClosed()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post('/acara/'.$event->slug.'/daftar', $this->attendee())
            ->assertSessionHas('error', __('events.error_closed'));

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_enrollment_in_a_past_event_is_rejected(): void
    {
        $event = Event::factory()->past()->create();

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post('/acara/'.$event->slug.'/daftar', $this->attendee())
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
