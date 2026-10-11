<?php

namespace App\Notifications\Customer;

use App\Support\Locale;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * Email verification for the `customer` guard.
 *
 * Queued, and that matters more than it looks: at launch ~500 people register
 * within minutes, and mail providers throttle. A synchronous send would hold a
 * PHP worker per registration waiting on an external API. O6 adds the job-level
 * rate limiting that keeps the burst inside the provider's ceiling.
 *
 * The signed route is built explicitly because the framework default points at
 * 'verification.verify' on the default guard; customers have their own route.
 */
class VerifyEmail extends BaseVerifyEmail implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    /**
     * Keep verification mail ahead of non-critical background work during a
     * registration burst. The worker listens to mail before default.
     *
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['mail' => 'mail'];
    }

    protected function verificationUrl($notifiable): string
    {
        // Build the route name for the current locale explicitly. Laravel's
        // URL::temporarySignedRoute expects a route name, and our public routes
        // are namespaced per locale ('en.customer.verification.verify'), so we
        // use Locale::name() to get the correct one for the active locale.
        $routeName = Locale::name('customer.verification.verify');

        return URL::temporarySignedRoute(
            $routeName,
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('customer.verify_subject'))
            ->greeting(__('customer.greeting', ['name' => $notifiable->name]))
            ->line(__('customer.verify_intro'))
            ->action(__('customer.verify_action'), $this->verificationUrl($notifiable))
            ->line(__('customer.verify_outro'));
    }
}
