<?php

namespace App\Enums;

enum IpcrStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case WithDean = 'with_dean';
    case WithHr = 'with_hr';
    case Returned = 'returned';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::WithDean => 'With Dean/Director',
            self::WithHr => 'With HR',
            self::Returned => 'Returned',
            self::Completed => 'Completed',
        };
    }

    /** Bootstrap-style badge class used across the screens. */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-status-draft',
            self::Submitted, self::WithDean, self::WithHr => 'badge-status-review',
            self::Returned => 'badge-status-returned',
            self::Completed => 'badge-status-completed',
        };
    }

    /** The reviewer role that holds an IPCR in this status, if any. */
    public function reviewerRole(): ?Role
    {
        return match ($this) {
            self::Submitted => Role::DepartmentHead,
            self::WithDean => Role::Dean,
            self::WithHr => Role::Hr,
            default => null,
        };
    }

    public function isEditableByEmployee(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    /** Statuses that count as "submitted" (the employee has handed it in at least once and it is not back with them). */
    public function isInReview(): bool
    {
        return $this->reviewerRole() !== null;
    }
}
