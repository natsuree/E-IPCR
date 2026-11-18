<?php

namespace App\Services;

use App\Enums\IpcrStatus;
use App\Enums\Role;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\IpcrTemplate;
use App\Models\RatingPeriod;
use App\Models\User;
use App\Notifications\IpcrNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Employee → Department Head → Dean/Director → VPAA → HR → Completed.
 * HR is the final approver. A returned IPCR restarts at the Department Head.
 *
 * Every action runs in a transaction, re-reads the IPCR with a lock, and moves
 * the status with a conditional update, so a double click or two reviewers
 * acting at once cannot act on the same step twice.
 *
 * An IPCR is never sent to a stage that has no active reviewer: the action is
 * refused with a message naming the missing reviewer or assignment.
 */
class IpcrWorkflow
{
    /** Create a draft for the open period by copying the published template's items. */
    public function create(User $employee, ?RatingPeriod $period = null): Ipcr
    {
        $period ??= RatingPeriod::open();
        $type = $employee->employee_type ?? $employee->role->employeeType();

        if (! $employee->active || ! $employee->isEmployee() || ! $period || ! $type) {
            throw ValidationException::withMessages(['ipcr' => 'No rating period is open right now, so a new IPCR cannot be created. HR opens rating periods; check again later.']);
        }

        $existing = Ipcr::where('user_id', $employee->id)->where('rating_period_id', $period->id)->first();
        if ($existing) {
            return $existing;
        }

        if ($period->roster_set_at && ! $period->roster()->whereKey($employee->id)->exists()) {
            throw ValidationException::withMessages(['ipcr' => 'HR has not assigned you to this evaluation period.']);
        }

        return DB::transaction(function () use ($employee, $period, $type) {
            RatingPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $existing = Ipcr::where('user_id', $employee->id)->where('rating_period_id', $period->id)->first();
            if ($existing) {
                return $existing;
            }
            $template = IpcrTemplate::activeFor($period->id, $type, $employee);
            if (! $template) {
                throw ValidationException::withMessages(['ipcr' => 'HR has not published the '.($type === 'teaching' ? 'Teaching' : 'Non-teaching').' IPCR template for '.$period->name.' yet. Try again after HR publishes it.']);
            }

            $ipcr = Ipcr::create([
                'user_id' => $employee->id,
                'rating_period_id' => $period->id,
                'ipcr_template_id' => $template->id,
                'status' => IpcrStatus::Draft,
            ]);

            foreach ($template->items as $item) {
                $ipcr->items()->create($item->only(['section', 'program_outcome', 'indicator', 'target', 'sort_order']));
            }

            Audit::log('ipcr.created', "{$employee->name} created an IPCR for {$period->name} (template version {$template->version}).", $ipcr, by: $employee);

            return $ipcr;
        });
    }

    /**
     * Submit or resubmit. The owner may do this whatever their current role, so a
     * promoted or reassigned employee can still finish their own IPCR.
     */
    public function submit(Ipcr $ipcr, User $owner): void
    {
        DB::transaction(function () use ($ipcr, $owner) {
            $ipcr = $this->lock($ipcr);

            if (! $owner->active || $ipcr->user_id !== $owner->id || ! $ipcr->status->isEditableByEmployee()) {
                throw ValidationException::withMessages(['ipcr' => 'This IPCR has already been submitted. Check Status Tracking to see where it is.']);
            }

            $missing = $this->missingAccomplishments($ipcr);
            if ($missing) {
                throw ValidationException::withMessages(['ipcr' => 'Enter your actual accomplishment for every indicator before submitting ('.count($missing).' still empty). The empty fields are highlighted below.'] + $missing);
            }

            $role = Role::DepartmentHead;
            if ($reason = $this->missingReviewer($ipcr, $role)) {
                throw ValidationException::withMessages(['ipcr' => 'Your IPCR cannot be submitted yet. '.$reason.' Your entries are saved.']);
            }

            $resubmit = $ipcr->status === IpcrStatus::Returned;

            $this->moveStatus($ipcr, [IpcrStatus::Draft, IpcrStatus::Returned], $role->reviewStatus(), ['submitted_at' => now()] + $this->employeeSnapshot($owner, $ipcr));
            $ipcr->reviews()->create([
                'user_id' => $owner->id,
                'role' => $owner->role,
                'action' => $resubmit ? 'resubmitted' : 'submitted',
            ]);

            Audit::log('ipcr.'.($resubmit ? 'resubmitted' : 'submitted'), "{$owner->name} ".($resubmit ? 'resubmitted' : 'submitted')." their IPCR for {$ipcr->period->name} to the {$role->label()}.", $ipcr, by: $owner);

            $owner->notify(new IpcrNotification(
                'Your IPCR for '.$ipcr->period->name.' was '.($resubmit ? 'resubmitted' : 'submitted').' to the '.$role->label().'.',
                'employee.tracking',
                ['ipcr' => $ipcr->id],
                'Your IPCR was '.($resubmit ? 'resubmitted' : 'submitted'),
            ));
            $this->notifyReviewers($ipcr, $role);
        });
    }

    /**
     * @param  array<int, array{rating?: int|null, remarks?: string|null}>  $ratings  keyed by item id
     */
    public function approve(Ipcr $ipcr, User $reviewer, array $ratings, ?string $remarks): void
    {
        DB::transaction(function () use ($ipcr, $reviewer, $ratings, $remarks) {
            $ipcr = $this->lock($ipcr);
            $this->assertAtStep($ipcr, $reviewer);
            $from = $ipcr->status;

            $this->saveRatings($ipcr, $ratings);

            if ($reviewer->role === Role::Hr) {
                $result = FinalRating::compute($ipcr->items()->get());
                if ($result['overall'] === null || $ipcr->items()->whereNull('rating')->exists()) {
                    throw ValidationException::withMessages(['ipcr' => 'Rate the indicators before giving final approval.']);
                }

                $this->moveStatus($ipcr, [$from], IpcrStatus::Completed, [
                    'final_rating' => $result['overall'],
                    'adjectival_rating' => $result['adjectival'],
                    'section_averages' => $result['sections'],
                    'rating_basis' => $result['basis'],
                    'completed_at' => now(),
                ]);
                $this->recordDecision($ipcr, $reviewer, 'completed', $remarks);

                $rating = number_format((float) $result['overall'], 2).' ('.$result['adjectival'].')';
                Audit::log('ipcr.completed', "{$reviewer->name} gave final approval to {$ipcr->user->name}'s IPCR for {$ipcr->period->name}. Final rating {$rating}.", $ipcr, by: $reviewer);

                $ipcr->user->notify(new IpcrNotification(
                    'Your IPCR for '.$ipcr->period->name.' was approved by HR and is completed. Final rating: '.$rating.'.',
                    'employee.tracking',
                    ['ipcr' => $ipcr->id],
                    'Your IPCR is completed',
                ));

                return;
            }

            $next = match ($reviewer->role) {
                Role::DepartmentHead => Role::Dean,
                Role::Dean => Role::Vpaa,
                Role::Vpaa => Role::Hr,
            };

            if ($reason = $this->missingReviewer($ipcr, $next)) {
                throw ValidationException::withMessages(['ipcr' => 'This IPCR cannot be forwarded yet. '.$reason.' Your ratings were not saved; you can still return it to the employee.']);
            }

            $this->moveStatus($ipcr, [$from], $next->reviewStatus());
            $this->recordDecision($ipcr, $reviewer, 'approved', $remarks);

            Audit::log('ipcr.approved', "{$reviewer->name} ({$reviewer->role->label()}) approved {$ipcr->user->name}'s IPCR for {$ipcr->period->name} and forwarded it to {$this->stepName($next)}.", $ipcr, by: $reviewer);

            $ipcr->user->notify(new IpcrNotification(
                'Your IPCR was approved by the '.$reviewer->role->label().' and forwarded to '.$this->stepName($next).'.',
                'employee.tracking',
                ['ipcr' => $ipcr->id],
                'Your IPCR was approved and forwarded',
            ));
            $this->notifyReviewers($ipcr, $next);
        });
    }

    public function return(Ipcr $ipcr, User $reviewer, array $ratings, string $remarks): void
    {
        DB::transaction(function () use ($ipcr, $reviewer, $ratings, $remarks) {
            $ipcr = $this->lock($ipcr);
            $this->assertAtStep($ipcr, $reviewer);

            if (trim($remarks) === '') {
                throw ValidationException::withMessages(['remarks' => 'Describe the required correction.']);
            }
            $this->saveRatings($ipcr, $ratings);
            $this->moveStatus($ipcr, [$ipcr->status], IpcrStatus::Returned);
            $this->recordDecision($ipcr, $reviewer, 'returned', $remarks);

            Audit::log('ipcr.returned', "{$reviewer->name} ({$reviewer->role->label()}) returned {$ipcr->user->name}'s IPCR for {$ipcr->period->name} for correction.", $ipcr, by: $reviewer);

            $ipcr->user->notify(new IpcrNotification(
                'Your IPCR was returned by the '.$reviewer->role->label().'. Read the remarks, then revise and resubmit.',
                'employee.tracking',
                ['ipcr' => $ipcr->id],
                'Your IPCR was returned for correction',
            ));
        });
    }

    /**
     * Remove a target the employee added. Never applies to HR template rows.
     * A target that reviewers have already seen is kept, marked as removed, so the
     * review history still makes sense; an unreviewed one is deleted.
     */
    public function removeTarget(Ipcr $ipcr, int $itemId, User $owner): IpcrItem
    {
        return DB::transaction(function () use ($ipcr, $itemId, $owner) {
            $ipcr = $this->lock($ipcr);
            if ($ipcr->user_id !== $owner->id || ! $ipcr->status->isEditableByEmployee()) {
                throw ValidationException::withMessages(['ipcr' => 'This IPCR is with the reviewers, so its targets cannot be changed now.']);
            }

            $item = $ipcr->items()->whereKey($itemId)->first();
            abort_unless($item, 404);
            abort_unless($item->isEmployeeTarget(), 403, 'Targets from the HR template cannot be removed.');

            $reviewed = $ipcr->reviews()->exists() || $item->rating !== null || filled($item->remarks);
            if ($reviewed) {
                $item->forceFill(['retired' => true, 'removed_at' => now()])->save();
            } else {
                $item->delete();
            }

            Audit::log('ipcr.target_removed', "{$owner->name} removed the added target \"".Str::limit($item->indicator, 80)."\" from their IPCR for {$ipcr->period->name}".($reviewed ? ' (kept in the history because it had been reviewed).' : '.'), $ipcr, by: $owner);

            return $item;
        });
    }

    /**
     * Errors keyed by field, one per target with no accomplishment, naming the section and number shown on screen.
     *
     * @return array<string, string>
     */
    public function missingAccomplishments(Ipcr $ipcr): array
    {
        $errors = [];
        foreach ($ipcr->items()->get()->groupBy('section') as $section => $items) {
            foreach ($items->values() as $n => $item) {
                if (trim((string) $item->actual_accomplishment) === '') {
                    $errors["items.{$item->id}.actual_accomplishment"] = 'Enter the actual accomplishment for '.self::targetLabel($section, $n + 1, $item->indicator).'.';
                }
            }
        }

        return $errors;
    }

    /** "Core Functions indicator 2 (“Prepares or revises syllabi…”)" */
    public static function targetLabel(string $section, int $number, string $indicator): string
    {
        return (Ipcr::SECTIONS[$section] ?? ucfirst($section)).' indicator '.$number.' ("'.Str::limit($indicator, 60).'")';
    }

    /**
     * Why nobody can act on the IPCR at $role's stage, or null when someone can.
     * Keeps every stage; the Administrator fixes the assignment.
     */
    public function missingReviewer(Ipcr $ipcr, Role $role): ?string
    {
        if ($this->reviewersFor($ipcr, $role)->isNotEmpty()) {
            return null;
        }

        $employee = $ipcr->user;

        return match ($role) {
            Role::DepartmentHead => $employee->department_id === null
                ? "No Department Head can review it because {$employee->name}'s account has no department assigned. The Administrator must assign a department under Users."
                : 'No active Department Head (other than the IPCR owner) is assigned to '.($employee->department?->name ?? 'the department').'. The Administrator must assign one under Users.',
            Role::Dean => $employee->college_id === null
                ? "No Dean/Director can review it because {$employee->name}'s account has no college or office assigned. The Administrator must assign one under Users."
                : 'No active Dean/Director (other than the IPCR owner) is assigned to '.($employee->college?->name ?? 'the college or office').'. The Administrator must assign one under Users.',
            Role::Vpaa => 'No active VPAA account (other than the IPCR owner) exists. The Administrator must assign the VPAA role under Users.',
            Role::Hr => 'No active HR Personnel account (other than the IPCR owner) exists. The Administrator must assign the HR role under Users.',
            default => null,
        };
    }

    /** Re-read the IPCR inside the transaction (row lock where the database supports it). */
    private function lock(Ipcr $ipcr): Ipcr
    {
        return Ipcr::whereKey($ipcr->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Change the status only if it is still one of $from. If another request
     * changed it first, nothing is updated and the whole action is rolled back.
     *
     * @param  list<IpcrStatus>  $from
     */
    private function moveStatus(Ipcr $ipcr, array $from, IpcrStatus $to, array $extra = []): void
    {
        $updated = Ipcr::whereKey($ipcr->getKey())
            ->whereIn('status', array_map(fn (IpcrStatus $s) => $s->value, $from))
            ->update(['status' => $to->value, 'updated_at' => now()] + $this->dehydrate($extra));

        if ($updated !== 1) {
            throw ValidationException::withMessages(['ipcr' => 'Someone else acted on this IPCR a moment ago. Open the Review Queue again to see its current status.']);
        }

        $ipcr->forceFill(['status' => $to] + $extra)->syncOriginal();
    }

    private function dehydrate(array $values): array
    {
        return array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $values);
    }

    private function assertAtStep(Ipcr $ipcr, User $reviewer): void
    {
        if ($ipcr->user_id === $reviewer->id) {
            throw ValidationException::withMessages(['ipcr' => 'You cannot review or approve your own IPCR.']);
        }
        if (! $reviewer->active || $ipcr->status->reviewerRole() !== $reviewer->role || ! $reviewer->covers($ipcr->user)) {
            throw ValidationException::withMessages(['ipcr' => 'This IPCR is no longer waiting for your review. Someone may have acted on it already; open the Review Queue again.']);
        }
    }

    private function saveRatings(Ipcr $ipcr, array $ratings): void
    {
        foreach ($ipcr->items()->get() as $item) {
            if (! array_key_exists($item->id, $ratings)) {
                continue;
            }
            $item->update([
                'rating' => $ratings[$item->id]['rating'] ?? null,
                'remarks' => $ratings[$item->id]['remarks'] ?? null,
            ]);
        }
    }

    /** The decision plus the per-target ratings and remarks as they stood when it was made. */
    private function recordDecision(Ipcr $ipcr, User $reviewer, string $action, ?string $remarks): void
    {
        $snapshot = [];
        foreach ($ipcr->items()->get()->groupBy('section') as $section => $items) {
            foreach ($items->values() as $n => $item) {
                $snapshot[] = [
                    'item_id' => $item->id,
                    'section' => $section,
                    'number' => $n + 1,
                    'indicator' => $item->indicator,
                    'rating' => $item->rating,
                    'remarks' => $item->remarks,
                ];
            }
        }

        $ipcr->reviews()->create([
            'user_id' => $reviewer->id,
            'role' => $reviewer->role,
            'action' => $action,
            'remarks' => $remarks,
            'item_ratings' => $snapshot,
        ]);
    }

    /** Employee details as they are at submission, saved on the IPCR for reports and printed records. */
    private function employeeSnapshot(User $owner, Ipcr $ipcr): array
    {
        $owner->loadMissing(['college', 'department']);

        return [
            'snapshot_taken_at' => now(),
            'snapshot_name' => $owner->name,
            'snapshot_employee_id' => $owner->employee_id,
            'snapshot_position' => $owner->position,
            // A promoted owner has no employee type now; the IPCR's template says which form they filed.
            'snapshot_employee_type' => $owner->employee_type ?? $owner->role->employeeType() ?? $ipcr->template?->employee_type,
            'snapshot_designation' => $owner->designation,
            'snapshot_college_id' => $owner->college_id,
            'snapshot_department_id' => $owner->department_id,
            'snapshot_college_name' => $owner->college?->name,
            'snapshot_department_name' => $owner->department?->name,
        ];
    }

    /** Active accounts holding $role whose unit covers the employee, never the owner. The queue goes by role and unit, not by name. */
    public function reviewersFor(Ipcr $ipcr, Role $role): Collection
    {
        $employee = $ipcr->user;

        return User::where('role', $role->value)
            ->where('active', true)
            ->whereKeyNot($employee->id)
            ->when($role === Role::DepartmentHead, fn ($q) => $q->where('department_id', $employee->department_id)->whereNotNull('department_id'))
            ->when($role === Role::Dean, fn ($q) => $q->where('college_id', $employee->college_id)->whereNotNull('college_id'))
            ->get();
    }

    private function notifyReviewers(Ipcr $ipcr, Role $role): void
    {
        foreach ($this->reviewersFor($ipcr, $role) as $reviewer) {
            $reviewer->notify(new IpcrNotification(
                'An IPCR is waiting for your review: '.$ipcr->user->name.' ('.$ipcr->period->name.').',
                $role->prefix().'.review',
                ['ipcr' => $ipcr->id],
                'An IPCR is waiting for your review',
            ));
        }
    }

    private function stepName(Role $role): string
    {
        return $role === Role::Hr ? 'HR' : 'the '.$role->label();
    }
}
