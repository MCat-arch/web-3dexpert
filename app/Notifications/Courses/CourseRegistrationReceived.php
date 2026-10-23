<?php

namespace App\Notifications\Courses;

use App\Models\CourseEnrollment;
use App\Support\Locale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a paid course registration creates a pending enrollment.
 *
 * The payment row is part of the message so the student can resume checkout
 * from their inbox instead of hunting for the order page again.
 */
class CourseRegistrationReceived extends Notification implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public CourseEnrollment $enrollment)
    {
        $this->enrollment->loadMissing(['course', 'payment']);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->enrollment->course;
        $payment = $this->enrollment->payment;
        $title = $course?->getTranslation('title', app()->getLocale()) ?? 'Kursus';

        $message = (new MailMessage)
            ->subject(__('courses.mail_registration_subject', ['course' => $title]))
            ->greeting(__('customer.greeting', ['name' => $this->enrollment->student_name]))
            ->line(__('courses.mail_registration_intro', ['course' => $title]));

        if ($payment) {
            $message
                ->line(__('courses.mail_registration_order', ['order' => $payment->order_id]))
                ->line(__('courses.mail_registration_total', [
                    'amount' => 'Rp '.number_format((float) $payment->gross_amount, 0, ',', '.'),
                ]))
                ->line(__('courses.mail_registration_next'));
        }

        $url = $payment
            ? Locale::route('payments.show', ['order_id' => $payment->order_id])
            : ($course ? Locale::route('courses.show', ['course' => $course->slug]) : url('/'));

        return $message->action(__('courses.mail_registration_action'), $url);
    }
}
