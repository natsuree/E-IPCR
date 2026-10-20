<?php

namespace App\Services;

use App\Enums\IpcrStatus;
use App\Enums\Role;
use App\Models\Ipcr;
use App\Models\IpcrTemplate;
use App\Models\RatingPeriod;
use App\Models\User;
use App\Notifications\IpcrNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Employee → Department Head → Dean/Director → HR → Completed.
 * HR is the final approver. A returned IPCR restarts at the Department Head.
 *
 * Every action runs in a transaction, re-reads the IPCR with a lock, and moves
 * the status with a conditional update, so a double click or two reviewers
 * acting at once cannot act on the same step twice.
 */
class IpcrWorkflow
{
    /** Reviewer steps in order. */
    private const CHAIN = [Role::DepartmentHead, Role::Dean, Role::Hr];

    /** Create a draft for the open period by copying the published template's items. */
    public function create(User $employee, ?RatingPeriod $period = null): Ipcr
    {
        $period ??= RatingPeriod::open();
        $type = $employee->employee_type ?? $employee->role->employeeType();

        if (! $period || ! $type) {
            throw ValidationException::withMessages(['ipcr' => 'No rating period is open right now, so a new IPCR cannot be created. HR opens rating periods; check again later.']);
        }

        $existing = Ipcr::where('user_id', $employee->id)->where('rating_period_id', $period->id)->first();
        if ($existing) {
            return $existing;
        }

        $template = IpcrTemplate::activeFor($period->id, $type);
        if (! $template) {
            throw ValidationException::withMessages(['ipcr' => 'HR has not published the '.($type === 'teaching' ? 'Teaching' : 'Non-teaching').' IPCR template for '.$period->name.' yet. Try again after HR publishes it.']);
        }

        return DB::transaction(function () use ($employee, $period, $template) {
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

    public function submit(Ipcr $ipcr, User $employee): void
    {
        DB::transaction(function () use ($ipcr, $employee) {
            $ipcr = $this->lock($ipcr);

            if (! $ipcr->status->isEditableByEmployee()) {
                throw ValidationException::withMessages(['ipcr' => 'This IPCR has already been submitted. Check Status Tracking to see where it is.']);
            }

            $missing = $ipcr->items()->where(fn ($q) => $q->whereNull('actual_accomplishment')->orWhere('actual_accomplishment', ''))->count();
            if ($missing > 0) {
                throw ValidationException::withMessages(['ipcr' => "Enter your actual accomplishment for every indicator before submitting ({$missing} still empty)."]);
            }

            $resubmit = $ipcr->status === IpcrStatus::Returned;
            [$role, $skipped] = $this->nextReviewer($ipcr, null);

            $this->moveStatus($ipcr, [IpcrStatus::Draft, IpcrStatus::Returned], $role->reviewStatus(), ['submitted_at' => now()]);
            $ipcr->reviews()->create([
                'user_id' => $employee->id,
                'role' => $employee->role,
                'action' => $resubmit ? 'resubmitted' : 'submitted',
                'remarks' => $this->skipNote($skipped, $ipcr),
            ]);

            Audit::log('ipcr.'.($resubmit ? 'resubmitted' : 'submitted'), "{$employee->name} ".($resubmit ? 'resubmitted' : 'submitted')." their IPCR for {$ipcr->period->name} to the {$role->label()}.", $ipcr, by: $employee);

            $employee->notify(new IpcrNotification(
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
                if ($result['overall'] === null) {
                    throw ValidationException::withMessages(['ipcr' => 'Rate the indicators before giving final approval.']);
                }

                $this->moveStatus($ipcr, [$from], IpcrStatus::Completed, [
                    'final_rating' => $result['overall'],
                    'adjectival_rating' => $result['adjectival'],
                    'section_averages' => $result['sections'],
                    'rating_basis' => $result['basis'],
                    'completed_at' => now(),
                ]);
                $ipcr->reviews()->create([
                    'user_id' => $reviewer->id, 'role' => $reviewer->role, 'action' => 'completed', 'remarks' => $remarks,
                ]);

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

            [$next, $skipped] = $this->nextReviewer($ipcr, $reviewer->role);
            $note = $this->skipNote($skipped, $ipcr);

            $this->moveStatus($ipcr, [$from], $next->reviewStatus());
            $ipcr->reviews()->create([
                'user_id' => $reviewer->id,
                'role' => $reviewer->role,
                'action' => 'approved',
                'remarks' => trim(($remarks ?? '').($note ? "\n".$note : '')) ?: null,
            ]);

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

            $this->saveRatings($ipcr, $ratings);
            $this->moveStatus($ipcr, [$ipcr->status], IpcrStatus::Returned);
            $ipcr->reviews()->create([
                'user_id' => $reviewer->id, 'role' => $reviewer->role, 'action' => 'returned', 'remarks' => $remarks,
            ]);

            Audit::log('ipcr.returned', "{$reviewer->name} ({$reviewer->role->label()}) returned {$ipcr->user->name}'s IPCR for {$ipcr->period->name} for correction.", $ipcr, by: $reviewer);

            $ipcr->user->notify(new IpcrNotification(
                'Your IPCR was returned by the '.$reviewer->role->label().'. Read the remarks, then revise and resubmit.',
                'employee.tracking',
                ['ipcr' => $ipcr->id],
                'Your IPCR was returned for correction',
            ));
        });
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
        if ($ipcr->status->reviewerRole() !== $reviewer->role || ! $reviewer->covers($ipcr->user)) {
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

    /**
     * The next reviewer step after $from (null = the employee), skipping a
     * Department Head or Dean/Director step when no active account covers the
     * employee's unit (for example offices with no department). HR always reviews.
     *
     * @return array{0: Role, 1: list<Role>}
     */
    public function nextReviewer(Ipcr $ipcr, ?Role $from): array
    {
        $start = $from === null ? 0 : array_search($from, self::CHAIN, true) + 1;
        $skipped = [];

        for ($i = $start; $i < count(self::CHAIN); $i++) {
            $role = self::CHAIN[$i];
            if ($role === Role::Hr || $this->reviewersFor($ipcr, $role)->isNotEmpty()) {
                return [$role, $skipped];
            }
            $skipped[] = $role;
        }

        return [Role::Hr, $skipped];
    }

    /** Active accounts holding $role whose unit covers the employee. The queue goes by role and unit, not by name. */
    public function reviewersFor(Ipcr $ipcr, Role $role): Collection
    {
        $employee = $ipcr->user;

        return User::where('role', $role->value)
            ->where('active', true)
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

    private function skipNote(array $skipped, Ipcr $ipcr): ?string
    {
        if (! $skipped) {
            return null;
        }

        $names = implode(' and ', array_map(fn (Role $r) => $r->label(), $skipped));

        return "Forwarded past the {$names} step: no active {$names} account is assigned to {$ipcr->user->unitLabel()}.";
    }

    private function stepName(Role $role): string
    {
        return $role === Role::Hr ? 'HR' : 'the '.$role->label();
    }
}
