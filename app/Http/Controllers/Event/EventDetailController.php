<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventDetailController extends Controller
{
    public function __invoke(Request $request, Event $event): View
    {
        abort_unless($event->is_published, 404);

        $customer = $request->user('customer');

        return view('events.show', [
            'event' => $event,
            'customer' => $customer,
            // Drives the CTA state. Resolved here rather than in the view so
            // Blade stays free of queries (guide.md section 3).
            'registration' => $customer
                ? EventRegistration::where('event_id', $event->getKey())
                    ->where('customer_id', $customer->getKey())
                    ->first()
                : null,
        ]);
    }
}
