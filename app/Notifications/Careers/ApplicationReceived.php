<?php

namespace App\Notifications\Careers;

use App\Models\JobApplication;
use App\Support\Locale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationReceived extends Notification implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public JobApplication $application) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('careers.mail_received_subject', ['position' => $this->application->vacancy->title]))
            ->greeting(__('customer.greeting', ['name' => $this->application->applicant_name]))
            ->line(__('careers.mail_received_intro', ['position' => $this->application->vacancy->title]))
            ->line(__('careers.mail_received_next'))
            ->action(__('careers.mail_action'), Locale::route('customer.applications'));
    }
}
