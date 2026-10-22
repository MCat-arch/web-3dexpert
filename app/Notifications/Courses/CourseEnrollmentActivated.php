<?php

namespace App\Notifications\Courses;

use App\Models\CourseEnrollment;
use App\Support\Locale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once an enrollment becomes usable: free registration, successful
 * payment, or staff-granted access.
 */
class CourseEnrollmentActivated extends Notification implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public CourseEnrollment $enrollment)
    {
        $this->enrollment->loadMissing('course');
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->enrollment->course;
        $title = $course?->getTranslation('title', app()->getLocale()) ?? 'Kursus';

        $url = $course
            ? Locale::route('courses.player.show', ['course' => $course->slug])
            : Locale::route('customer.courses');

        return (new MailMessage)
            ->subject(__('courses.mail_active_subject', ['course' => $title]))
            ->greeting(__('customer.greeting', ['name' => $this->enrollment->student_name]))
            ->line(__('courses.mail_active_intro', ['course' => $title]))
            ->action(__('courses.mail_active_action'), $url);
    }
}
