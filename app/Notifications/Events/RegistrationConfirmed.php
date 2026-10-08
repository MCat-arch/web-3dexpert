<?php

namespace App\Notifications\Events;

use App\Models\EventRegistration;
use App\Support\Locale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued, like every other mail in this application.
 *
 * At launch ~500 people enroll in one event within minutes. A synchronous send
 * would hold a PHP-FPM worker per registration waiting on an external mail
 * API, and workers are the scarce resource (~35, not 500).
 */
class RegistrationConfirmed extends Notification implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public EventRegistration $registration) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;

        $message = (new MailMessage)
            ->subject(__('events.mail_subject', ['event' => $event->title]))
            ->greeting(__('customer.greeting', ['name' => $this->registration->attendee_name]))
            ->line(__('events.mail_intro', ['event' => $event->title]))
            ->line(__('events.mail_when', [
                'when' => $event->starts_at->translatedFormat('l, j F Y, H:i'),
            ]));

        if ($event->venue) {
            $message->line(__('events.mail_where', ['where' => $event->venue]));
        }

        // The attendee needs this to check in, so it goes in the body rather
        // than only on a page they have to find again.
        return $message
            ->line(__('events.mail_ticket', ['code' => $this->registration->ticket_code]))
            ->action(__('events.mail_action'), Locale::route('customer.events'));
    }
}
