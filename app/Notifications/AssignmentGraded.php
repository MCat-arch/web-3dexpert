<?php

namespace App\Notifications;

use App\Models\AssignmentSubmission;
use App\Support\Locale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssignmentGraded extends Notification implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public AssignmentSubmission $submission)
    {
        $this->submission->loadMissing(['assignment', 'customer']);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $assignment = $this->submission->assignment;

        return (new MailMessage)
            ->subject(__('courses.assignment_graded_subject'))
            ->greeting(__('customer.greeting', ['name' => $this->submission->customer?->name ?? 'Siswa']))
            ->line(__('courses.assignment_graded_intro', [
                'assignment' => $assignment?->getTranslation('title', app()->getLocale()) ?? 'Tugas',
            ]))
            ->line(__('courses.assignment_score', [
                'score' => (string) $this->submission->score,
                'max' => (string) ($assignment?->max_score ?? 100),
            ]))
            ->when(filled($this->submission->feedback), fn (MailMessage $mail) => $mail
                ->line(__('courses.assignment_feedback_label'))
                ->line((string) $this->submission->feedback))
            ->action(__('courses.open_my_courses'), Locale::route('customer.courses'));
    }
}
