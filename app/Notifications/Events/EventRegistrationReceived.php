<?php

namespace App\Notifications\Events;

use App\Models\EventRegistration;
use App\Support\Locale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a paid event registration holds a seat while the customer pays.
 * The seat is released automatically if the payment expires.
 */
class EventRegistrationReceived extends Notification implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public EventRegistration $registration)
    {
        $this->registration->loadMissing(['event', 'payment']);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        $payment = $this->registration->payment;
        $title = $event?->getTranslation('title', app()->getLocale()) ?? 'Acara';

        $message = (new MailMessage)
            ->subject(__('events.mail_pending_subject', ['event' => $title]))
            ->greeting(__('customer.greeting', ['name' => $this->registration->attendee_name]))
            ->line(__('events.mail_pending_intro', ['event' => $title]))
            ->line(__('events.mail_pending_expiry', [
                'minutes' => (int) config('payments.expiry_minutes', 15),
            ]));

        if ($payment) {
            $message
                ->line(__('events.mail_pending_order', ['order' => $payment->order_id]))
                ->line(__('events.mail_pending_total', [
                    'amount' => 'Rp '.number_format((float) $payment->gross_amount, 0, ',', '.'),
                ]));
        }

        $url = $payment
            ? Locale::route('payments.show', ['order_id' => $payment->order_id])
            : ($event ? Locale::route('events.show', ['event' => $event->slug]) : url('/'));

        return $message->action(__('events.mail_pending_action'), $url);
    }
}
