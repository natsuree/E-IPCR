<?php

namespace App\Models;

use App\Enums\IpcrStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Ipcr extends Model
{
    use SoftDeletes;

    public const SECTIONS = [
        'strategic' => 'Strategic Functions',
        'core' => 'Core Functions',
        'support' => 'Support Functions',
    ];

    protected $fillable = [
        'user_id',
        'rating_period_id',
        'ipcr_template_id',
        'status',
        'final_rating',
        'adjectival_rating',
        'section_averages',
        'rating_basis',
        'submitted_at',
        'completed_at',
        'snapshot_taken_at',
        'snapshot_name',
        'snapshot_employee_id',
        'snapshot_position',
        'snapshot_employee_type',
        'snapshot_designation',
        'snapshot_college_id',
        'snapshot_department_id',
        'snapshot_college_name',
        'snapshot_department_name',
    ];

    protected function casts(): array
    {
        return [
            'status' => IpcrStatus::class,
            'final_rating' => 'decimal:2',
            'section_averages' => 'array',
            'rating_basis' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'snapshot_taken_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RatingPeriod::class, 'rating_period_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(IpcrTemplate::class, 'ipcr_template_id');
    }

    /** Template rows replaced when HR published a newer version (entries kept for reference). */
    public function retiredTargets(): HasMany
    {
        return $this->hasMany(IpcrItem::class)->where('retired', true)->whereNull('removed_at');
    }

    /** Employee-added targets the employee removed after the IPCR had been reviewed (kept for history). */
    public function removedTargets(): HasMany
    {
        return $this->hasMany(IpcrItem::class)->where('retired', true)->whereNotNull('removed_at');
    }

    // ----------------------------------------------------------------- employee details

    /** Whether the employee's details were saved when the IPCR was (re)submitted. */
    public function hasEmployeeSnapshot(): bool
    {
        return $this->snapshot_taken_at !== null;
    }

    /** Submitted before details were saved: the current account details are shown instead. */
    public function usesLegacyEmployeeDetails(): bool
    {
        return ! $this->hasEmployeeSnapshot() && $this->status !== IpcrStatus::Draft;
    }

    public function employeeName(): string
    {
        return $this->hasEmployeeSnapshot() ? (string) $this->snapshot_name : $this->user->name;
    }

    public function employeeNumber(): ?string
    {
        return $this->hasEmployeeSnapshot() ? $this->snapshot_employee_id : $this->user->employee_id;
    }

    public function employeePosition(): ?string
    {
        return $this->hasEmployeeSnapshot() ? $this->snapshot_position : $this->user->position;
    }

    public function employeeType(): ?string
    {
        return $this->hasEmployeeSnapshot() ? $this->snapshot_employee_type : ($this->user->employee_type ?? $this->template?->employee_type);
    }

    public function employeeTypeLabel(): string
    {
        return match ($this->employeeType()) {
            'teaching' => 'Teaching',
            'nonteaching' => 'Non-teaching',
            default => '—',
        };
    }

    /** "College of Engineering · Computer Engineering" as it was at submission. */
    public function employeeUnitLabel(): string
    {
        if (! $this->hasEmployeeSnapshot()) {
            return $this->user->unitLabel();
        }

        return collect([$this->snapshot_college_name, $this->snapshot_department_name])->filter()->implode(' · ') ?: 'No college or office assigned';
    }

    /** Why the IPCR is waiting with nobody able to act on it, or null. */
    public function stallReason(): ?string
    {
        $role = $this->status->reviewerRole();

        return $role ? app(\App\Services\IpcrWorkflow::class)->missingReviewer($this, $role) : null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(IpcrItem::class)->where('retired', false)->orderBy('sort_order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(IpcrReview::class)->orderBy('created_at')->orderBy('id');
    }

    public function latestReturn(): ?IpcrReview
    {
        return $this->reviews()->where('action', 'returned')->reorder()->latest('id')->first();
    }

    /** Items grouped by section key, in section order. */
    public function itemsBySection(): array
    {
        $grouped = [];
        foreach (array_keys(self::SECTIONS) as $section) {
            $grouped[$section] = $this->items->where('section', $section)->values();
        }

        return $grouped;
    }

    /** Reviews since the latest (re)submission: the current pass through the workflow. */
    public function currentCycle(): Collection
    {
        $reviews = $this->reviews->values();
        $start = $reviews->keys()->filter(fn ($k) => in_array($reviews[$k]->action, ['submitted', 'resubmitted'], true))->last();

        return $start === null ? collect() : $reviews->slice($start)->values();
    }

    /** Who signed each part of the signatory block in the current cycle. */
    public function signoffs(): array
    {
        $cycle = $this->currentCycle();
        $approvedBy = fn (Role $role) => $cycle->first(fn (IpcrReview $r) => $r->role === $role && in_array($r->action, ['approved', 'completed'], true));

        return [
            'ratee' => $cycle->first(),
            'reviewed_by' => $approvedBy(Role::DepartmentHead),
            'noted_by' => $approvedBy(Role::Dean),
            'vpaa' => $approvedBy(Role::Vpaa),
            'final_rating_by' => $approvedBy(Role::Hr),
        ];
    }

    /**
     * Status-tracking steps: Employee (Submitted), Department Head, Dean/Director, VPAA, HR Personnel.
     * A step that is due but has no active reviewer is shown as "stalled".
     *
     * @return list<array{label: string, state: string, note: string, review: ?IpcrReview}>
     */
    public function timeline(): array
    {
        $cycle = $this->currentCycle();
        $status = $this->status;
        $lastReturn = $status === IpcrStatus::Returned ? $this->reviews->where('action', 'returned')->last() : null;

        $steps = [[
            'label' => 'Employee (Submitted)',
            'state' => $status->isEditableByEmployee() ? 'current' : 'done',
            'note' => match (true) {
                $status === IpcrStatus::Draft => 'Draft, not yet submitted',
                $status === IpcrStatus::Returned => 'Returned for revision',
                default => 'Submitted '.$cycle->first()?->created_at->format('M j, Y'),
            },
            'review' => $cycle->first(),
        ]];

        $reached = $status === IpcrStatus::Completed;
        foreach (array_reverse([Role::DepartmentHead, Role::Dean, Role::Vpaa, Role::Hr]) as $role) {
            $done = $cycle->first(fn (IpcrReview $r) => $r->role === $role && in_array($r->action, ['approved', 'completed'], true));

            if ($lastReturn && $lastReturn->role === $role) {
                $state = 'returned';
                $note = 'Returned '.$lastReturn->created_at->format('M j, Y');
            } elseif ($done) {
                $state = 'done';
                $note = 'Approved '.$done->created_at->format('M j, Y');
            } elseif ($status->reviewerRole() === $role) {
                $state = 'current';
                $stall = $this->stallReason();
                $note = $stall ? 'Waiting · '.$stall : 'Reviewing now';
            } elseif ($reached) {
                $state = 'skipped';
                $note = 'No approval recorded';
            } else {
                $state = 'pending';
                $note = 'Waiting';
            }

            if (in_array($state, ['done', 'current', 'returned'], true)) {
                $reached = true;
            }

            $steps[$role->value] = [
                'label' => $role === Role::Hr ? 'HR Personnel' : $role->label(),
                'state' => $state,
                'note' => $note,
                'review' => $done ?? ($state === 'returned' ? $lastReturn : null),
            ];
        }

        // Restore display order: employee, department head, dean, VPAA, HR.
        return [$steps[0], $steps[Role::DepartmentHead->value], $steps[Role::Dean->value], $steps[Role::Vpaa->value], $steps[Role::Hr->value]];
    }

    /** IPCRs a user may see in lists: their own, or their unit's when they review. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isEmployee()) {
            return $query->where('user_id', $viewer->id);
        }

        if ($viewer->role === Role::Vpaa) {
            return $query->where('status', IpcrStatus::WithVpaa->value);
        }

        if ($viewer->role === Role::Admin) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('user', fn (Builder $q) => $q->withinScopeOf($viewer));
    }

    /** IPCRs currently waiting at this reviewer's step and inside their scope. */
    public function scopeAwaiting(Builder $query, User $reviewer): Builder
    {
        $status = $reviewer->role->reviewStatus();

        if (! $status) {
            return $query->whereRaw('1 = 0');
        }

        // Nobody reviews their own IPCR.
        return $query->visibleTo($reviewer)->where('ipcrs.status', $status->value)->where('ipcrs.user_id', '!=', $reviewer->id);
    }

    /** Reviewer stage => [role, the employee column the reviewer must share]. */
    public const STAGES = [
        'submitted' => ['depthead', 'department_id'],
        'with_dean' => ['dean', 'college_id'],
        'with_vpaa' => ['vpaa', null],
        'with_hr' => ['hr', null],
    ];

    /**
     * IPCRs waiting at a stage that no active account other than the owner can act on.
     * Uses the same rule as the workflow (role, active, unit), in SQL.
     */
    public function scopeStalled(Builder $query): Builder
    {
        return $query->where(function (Builder $any) {
            foreach (self::STAGES as $status => [$role, $column]) {
                $any->orWhere(function (Builder $q) use ($status, $role, $column) {
                    $q->where('ipcrs.status', $status)->whereNotExists(function ($r) use ($role, $column) {
                        $r->selectRaw('1')->from('users as r')
                            ->where('r.role', $role)->where('r.active', true)->whereNull('r.deleted_at')
                            ->whereColumn('r.id', '!=', 'ipcrs.user_id');
                        if ($column) {
                            $r->whereRaw("r.{$column} = (select e.{$column} from users as e where e.id = ipcrs.user_id)");
                        }
                    });
                });
            }
        });
    }
}
