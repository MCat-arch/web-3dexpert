<?php

namespace Tests\Feature\Events;

use App\Actions\Events\CancelEventRegistration;
use App\Actions\Events\EnrollInEvent;
use App\Exceptions\EnrollmentException;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Capacity is a concurrency problem, not a validation problem.
 *
 *     if ($event->registrations()->count() < $event->capacity) { ... }
 *
 * oversells: two requests both read 49 of 50 and both insert. EnrollInEvent
 * instead issues one conditional UPDATE and reads the affected-row count, so
 * the database arbitrates.
 *
 * ---------------------------------------------------------------------------
 * WHAT THESE TESTS DO NOT PROVE
 *
 * PHPUnit runs sequentially. These assert that the LOGIC is atomic -- that a
 * conditional update affects zero rows once the event is full, and that every
 * release path returns exactly one seat. They do NOT exercise real parallelism.
 *
 * Genuine concurrency verification is O6: 500 concurrent claims against one
 * event with capacity well below 500, asserting afterwards that seats_taken
 * equals capacity exactly. Until that runs, P5a's concurrency criterion is NOT
 * met, and saying otherwise would be the kind of unearned claim guide.md 10.6
 * forbids.
 * ---------------------------------------------------------------------------
 */
class EventCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function enroll(Event $event, Customer $customer): EventRegistration
    {
        return app(EnrollInEvent::class)($event, $customer);
    }

    public function test_the_last_seat_goes_to_exactly_one_person(): void
    {
        $event = Event::factory()->capacity(1)->create();

        $this->enroll($event, Customer::factory()->create());

        $this->expectException(EnrollmentException::class);
        $this->enroll($event, Customer::factory()->create());
    }

    public function test_seats_taken_never_exceeds_capacity_across_many_claims(): void
    {
        $capacity = 5;
        $event = Event::factory()->capacity($capacity)->create();

        $succeeded = 0;

        // Twice as many contenders as seats.
        foreach (range(1, $capacity * 2) as $i) {
            try {
                $this->enroll($event->fresh(), Customer::factory()->create());
                $succeeded++;
            } catch (EnrollmentException) {
                // Expected once the event is full.
            }
        }

        $this->assertSame($capacity, $succeeded);
        $this->assertSame($capacity, $event->fresh()->seats_taken);
        $this->assertSame($capacity, EventRegistration::occupying()->count());
    }

    public function test_the_claim_affects_no_rows_once_the_event_is_full(): void
    {
        // The mechanism itself, independent of the action: this is the single
        // statement everything else relies on.
        $event = Event::factory()->capacity(2)->create();

        $first = Event::whereKey($event->getKey())
            ->where(fn ($q) => $q->whereNull('capacity')->orWhereColumn('seats_taken', '<', 'capacity'))
            ->increment('seats_taken');

        $second = Event::whereKey($event->getKey())
            ->where(fn ($q) => $q->whereNull('capacity')->orWhereColumn('seats_taken', '<', 'capacity'))
            ->increment('seats_taken');

        $third = Event::whereKey($event->getKey())
            ->where(fn ($q) => $q->whereNull('capacity')->orWhereColumn('seats_taken', '<', 'capacity'))
            ->increment('seats_taken');

        $this->assertSame(1, $first);
        $this->assertSame(1, $second);
        $this->assertSame(0, $third, 'A full event must affect zero rows, not increment past capacity.');
        $this->assertSame(2, $event->fresh()->seats_taken);
    }

    public function test_an_unlimited_event_always_claims(): void
    {
        $event = Event::factory()->unlimited()->create();

        foreach (range(1, 10) as $i) {
            $this->enroll($event->fresh(), Customer::factory()->create());
        }

        $this->assertSame(10, $event->fresh()->seats_taken);
    }

    public function test_cancelling_returns_the_seat(): void
    {
        $event = Event::factory()->capacity(1)->create();
        $customer = Customer::factory()->create();

        $registration = $this->enroll($event, $customer);
        $this->assertSame(1, $event->fresh()->seats_taken);

        app(CancelEventRegistration::class)($registration);

        $this->assertSame(0, $event->fresh()->seats_taken);

        // The freed seat is genuinely usable again.
        $this->enroll($event->fresh(), Customer::factory()->create());
        $this->assertSame(1, $event->fresh()->seats_taken);
    }

    public function test_cancelling_twice_releases_only_one_seat(): void
    {
        // Without idempotence here, a double-submitted cancel form would hand
        // the event a seat it never had.
        $event = Event::factory()->capacity(5)->create();

        $registration = $this->enroll($event, Customer::factory()->create());
        $this->enroll($event->fresh(), Customer::factory()->create());

        $this->assertSame(2, $event->fresh()->seats_taken);

        app(CancelEventRegistration::class)($registration);
        app(CancelEventRegistration::class)($registration->fresh());

        $this->assertSame(1, $event->fresh()->seats_taken);
    }

    public function test_seats_taken_is_never_driven_below_zero(): void
    {
        $event = Event::factory()->capacity(5)->create();

        $registration = EventRegistration::factory()->for($event)->create();

        // seats_taken is 0 here: the registration was seeded, not enrolled.
        // A release that ignored the floor would wrap the unsigned column or
        // make seatsLeft() over-report availability.
        app(CancelEventRegistration::class)($registration);

        $this->assertSame(0, $event->fresh()->seats_taken);
    }

    public function test_a_cancelled_registration_can_be_reused_on_re_enrollment(): void
    {
        // The (event_id, customer_id) unique index has no portable partial
        // form in MySQL, so re-registering must UPDATE the cancelled row
        // rather than insert beside it.
        $event = Event::factory()->capacity(5)->create();
        $customer = Customer::factory()->create();

        $registration = $this->enroll($event, $customer);
        app(CancelEventRegistration::class)($registration);

        $again = $this->enroll($event->fresh(), $customer);

        $this->assertSame($registration->id, $again->id);
        $this->assertSame(1, EventRegistration::count());
        $this->assertSame(EventRegistration::STATUS_CONFIRMED, $again->fresh()->status);
        $this->assertSame(1, $event->fresh()->seats_taken);
    }

    public function test_a_rejected_claim_leaves_no_registration_behind(): void
    {
        $event = Event::factory()->capacity(1)->full()->create();

        try {
            $this->enroll($event, Customer::factory()->create());
        } catch (EnrollmentException) {
            // expected
        }

        $this->assertSame(0, EventRegistration::count());
        $this->assertSame(1, $event->fresh()->seats_taken, 'A refused claim must not change the counter.');
    }
}
