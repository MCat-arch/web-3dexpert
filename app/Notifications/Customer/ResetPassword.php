<?php

namespace App\Notifications\Customer;

use App\Support\Locale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset for the `customer` guard.
 *
 * Points at the customer reset route, not the framework default: the token
 * comes from the `customers` broker and its own token table, so redeeming it
 * on the staff form would fail -- and must keep failing (see config/auth.php).
 */
class ResetPassword extends Notification implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = Locale::route('customer.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(__('customer.reset_subject'))
            ->greeting(__('customer.greeting', ['name' => $notifiable->name]))
            ->line(__('customer.reset_intro'))
            ->action(__('customer.reset_action'), $url)
            ->line(__('customer.reset_expiry', [
                'count' => config('auth.passwords.customers.expire'),
            ]))
            ->line(__('customer.reset_outro'));
    }
}
