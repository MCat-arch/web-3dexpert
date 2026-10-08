<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\JobApplication;
use App\Models\User;

/**
 * Two different audiences with different rights over the same record, which is
 * why this policy is written for both model types rather than one.
 *
 * Applicants (Customer) own their application and may read its status and
 * withdraw it. They may NOT read their own uploaded documents back through the
 * staff route, and they may never see internal_notes.
 *
 * Staff (User) read everything including documents and notes, and move the
 * status. The tier split across editor/admin/superadmin is refined in P8; the
 * baseline here is that document access is staff-only.
 *
 * NOTE on invocation: always resolve the subject explicitly --
 * Gate::forUser($request->user('customer')) or ->forUser($request->user()).
 * Gate::authorize() and $this->authorize() read the DEFAULT guard, which is
 * `web`, so on a customer route the policy would receive a User (or nothing)
 * where it types Customer and fail closed for the real owner.
 */
class JobApplicationPolicy
{
    public function view(Customer|User $actor, JobApplication $application): bool
    {
        if ($actor instanceof User) {
            return $actor->canManageContent();
        }

        return $this->owns($actor, $application);
    }

    /**
     * Reading an applicant's uploaded files. Staff only.
     *
     * Deliberately not granted to the owning applicant: they already have the
     * file they uploaded, and every additional party who can reach a CV is
     * another way for it to leak. The route that uses this is staff-only, so
     * this is defence in depth rather than the only check.
     */
    public function viewDocuments(Customer|User $actor, JobApplication $application): bool
    {
        return $actor instanceof User && $actor->canManageContent();
    }

    /** Staff-only free text about a candidate. Never applicant-visible. */
    public function viewNotes(Customer|User $actor, JobApplication $application): bool
    {
        return $actor instanceof User && $actor->canManageContent();
    }

    public function updateStatus(Customer|User $actor, JobApplication $application): bool
    {
        return $actor instanceof User && $actor->canManageContent();
    }

    public function withdraw(Customer|User $actor, JobApplication $application): bool
    {
        return $actor instanceof Customer
            && $this->owns($actor, $application)
            && $application->isActive();
    }

    private function owns(Customer $customer, JobApplication $application): bool
    {
        // An application whose account was deleted has customer_id === null.
        // Guarding explicitly so a null-vs-null comparison can never grant
        // access to a stranger.
        return $application->customer_id !== null
            && $application->customer_id === $customer->getKey();
    }
}
