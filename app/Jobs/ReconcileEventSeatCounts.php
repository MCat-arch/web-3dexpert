<?php

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileEventSeatCounts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function handle(): void
    {
        Event::query()
            ->upcoming()
            ->orderBy('id')
            ->chunkById(100, function ($events): void {
                foreach ($events as $event) {
                    $this->reconcile((int) $event->getKey());
                }
            });
    }

    private function reconcile(int $eventId): void
    {
        $drift = DB::transaction(function () use ($eventId): ?array {
            $event = Event::query()->whereKey($eventId)->lockForUpdate()->first();

            if (! $event || ! $event->starts_at->isFuture()) {
                return null;
            }

            $counterBefore = (int) $event->seats_taken;
            $countedRegistrations = (int) $event->registrations()->occupying()->count();

            if ($counterBefore === $countedRegistrations) {
                return null;
            }

            Event::query()
                ->whereKey($eventId)
                ->update(['seats_taken' => $countedRegistrations]);

            return [
                'event_id' => $eventId,
                'counter_before' => $counterBefore,
                'counted_registrations' => $countedRegistrations,
                'counter_after' => $countedRegistrations,
            ];
        }, 3);

        if ($drift !== null) {
            // P8 adds the durable audit trail and staff notification channel.
            Log::alert('Event seat count drift corrected.', $drift);
        }
    }
}
