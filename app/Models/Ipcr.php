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

    public function items(): HasMany
    {
        return $this->hasMany(IpcrItem::class)->orderBy('sort_order')->orderBy('id');
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
            'final_rating_by' => $approvedBy(Role::Hr),
        ];
    }

    /**
     * Status-tracking steps: Employee (Submitted), Department Head, Dean/Director, HR Personnel.
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
        foreach (array_reverse([Role::DepartmentHead, Role::Dean, Role::Hr]) as $role) {
            $done = $cycle->first(fn (IpcrReview $r) => $r->role === $role && in_array($r->action, ['approved', 'completed'], true));

            if ($lastReturn && $lastReturn->role === $role) {
                $state = 'returned';
                $note = 'Returned '.$lastReturn->created_at->format('M j, Y');
            } elseif ($done) {
                $state = 'done';
                $note = 'Approved '.$done->created_at->format('M j, Y');
            } elseif ($status->reviewerRole() === $role) {
                $state = 'current';
                $note = 'Reviewing now';
            } elseif ($reached) {
                $state = 'skipped';
                $note = 'Not required for this unit';
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

        // Restore display order: employee, department head, dean, HR.
        return [$steps[0], $steps[Role::DepartmentHead->value], $steps[Role::Dean->value], $steps[Role::Hr->value]];
    }

    /** IPCRs a user may see in lists: their own, or their unit's when they review. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isEmployee()) {
            return $query->where('user_id', $viewer->id);
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

        return $query->visibleTo($reviewer)->where('status', $status->value);
    }
}
