<?php

namespace App\Policies;

use App\Models\AssignmentSubmission;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\User;

class AssignmentSubmissionPolicy
{
    public function viewAny(User|Customer|null $user): bool
    {
        if ($user instanceof User) {
            return $user->can(User::AREA_ASSIGNMENTS);
        }

        return $user instanceof Customer;
    }

    public function view(User|Customer|null $user, AssignmentSubmission $submission): bool
    {
        if ($user instanceof User) {
            return $user->can(User::AREA_ASSIGNMENTS);
        }

        if (! $user instanceof Customer) {
            return false;
        }

        return $this->ownsAndIsEnrolled($user, $submission);
    }

    public function download(User|Customer|null $user, AssignmentSubmission $submission): bool
    {
        return $this->view($user, $submission);
    }

    public function grade(User|Customer|null $user, AssignmentSubmission $submission): bool
    {
        return $user instanceof User && $user->can(User::AREA_ASSIGNMENTS);
    }

    private function ownsAndIsEnrolled(Customer $customer, AssignmentSubmission $submission): bool
    {
        if ((int) $submission->customer_id !== (int) $customer->getKey()) {
            return false;
        }

        $submission->loadMissing('assignment.lesson.section');

        $courseId = $submission->assignment?->lesson?->section?->course_id;

        if (! $courseId) {
            return false;
        }

        return CourseEnrollment::query()
            ->where('course_id', $courseId)
            ->where('customer_id', $customer->getKey())
            ->active()
            ->exists();
    }
}
