<?php

namespace App\Policies;

use App\Enums\IpcrStatus;
use App\Models\Ipcr;
use App\Models\User;

class IpcrPolicy
{
    /** Owner, or a reviewer whose scope covers the employee once the IPCR has been submitted. */
    public function view(User $user, Ipcr $ipcr): bool
    {
        if ($ipcr->user_id === $user->id) {
            return true;
        }

        return $user->role->isReviewer()
            && $ipcr->status !== IpcrStatus::Draft
            && $user->covers($ipcr->user);
    }

    /** The owner may edit only while it is a draft or after it was returned. */
    public function update(User $user, Ipcr $ipcr): bool
    {
        return $ipcr->user_id === $user->id && $ipcr->status->isEditableByEmployee();
    }

    /** Reviewers act only on IPCRs at their own step and inside their scope. */
    public function review(User $user, Ipcr $ipcr): bool
    {
        return $user->role->isReviewer()
            && $ipcr->status === $user->role->reviewStatus()
            && $user->covers($ipcr->user);
    }

    /** Completed IPCR records, read-only, inside the reviewer's scope. */
    public function viewRecord(User $user, Ipcr $ipcr): bool
    {
        return $user->role->isReviewer()
            && $ipcr->status === IpcrStatus::Completed
            && $user->covers($ipcr->user);
    }
}
