<?php

namespace Tests\Feature\Events;

use App\Jobs\ReconcileEventSeatCounts;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SeatReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_drifted_counter_is_corrected_and_alerted_with_both_values(): void
    {
        Log::spy();

        $event = Event::factory()->create(['seats_taken' => 0]);
        EventRegistration::factory()
            ->for($event)
            ->count(2)
            ->create(['status' => EventRegistration::STATUS_CONFIRMED]);

        app(ReconcileEventSeatCounts::class)->handle();

        $this->assertSame(2, $event->fresh()->seats_taken);
        Log::shouldHaveReceived('alert')
            ->once()
            ->with('Event seat count drift corrected.', [
                'event_id' => $event->getKey(),
                'counter_before' => 0,
                'counted_registrations' => 2,
                'counter_after' => 2,
            ]);
    }

    public function test_a_correct_counter_is_left_unchanged_without_an_alert(): void
    {
        Log::spy();

        $event = Event::factory()->create(['seats_taken' => 1]);
        EventRegistration::factory()
            ->for($event)
            ->create(['status' => EventRegistration::STATUS_CONFIRMED]);

        app(ReconcileEventSeatCounts::class)->handle();

        $this->assertSame(1, $event->fresh()->seats_taken);
        Log::shouldNotHaveReceived('alert');
    }

    public function test_past_events_are_not_reconciled(): void
    {
        $event = Event::factory()->past()->create(['seats_taken' => 0]);
        EventRegistration::factory()
            ->for($event)
            ->create(['status' => EventRegistration::STATUS_CONFIRMED]);

        app(ReconcileEventSeatCounts::class)->handle();

        $this->assertSame(0, $event->fresh()->seats_taken);
    }
}
