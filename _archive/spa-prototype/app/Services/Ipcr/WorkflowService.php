<?php

namespace App\Services\Ipcr;

use App\Models\IpcrRecord;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Port of dist/js/model.js workflow: validation, permissions, the review state
 * machine, submission snapshots, history and notification fan-out, plus the
 * organizational scope rules from progress.js (inScope).
 */
class WorkflowService
{
    public const STAGES = ['Department Head', 'Dean', 'HR Personnel', 'Vice President', 'President', 'Completed'];

    public const RETURNABLE_ROLES = ['Department Head', 'Dean', 'HR Personnel'];

    public function __construct(private readonly RatingService $rating)
    {
    }

    public static function nowIso(): string
    {
        return now()->format('Y-m-d\TH:i:s.v\Z');
    }

    /** Prototype makeRecord(): a fresh Draft with template rows. */
    public function createDraft(User $user, string $period, array $settings): IpcrRecord
    {
        $at = self::nowIso();

        $record = IpcrRecord::create([
            'id' => (string) Str::uuid(),
            'owner_id' => $user->id,
            'name' => $user->name,
            'position' => $user->position,
            'unit' => $user->unit,
            'department' => $user->department,
            'type' => $user->type,
            'period' => $period,
            'status' => 'Draft',
            'created' => $at,
            'updated' => $at,
            'commitment' => 'I commit to deliver and agree to be evaluated on the attainment of the following targets in accordance with the indicated measures.',
            'development' => '',
            'mfo' => null,
            'rating_settings' => $settings,
        ]);

        foreach (self::templateRows($user->type) as $row) {
            IpcrRowFactory::create($record, $row);
        }

        return $record->load(['rows', 'history', 'submissions']);
    }

    /** Prototype model.js templateRows(). */
    public static function templateRows(string $type): array
    {
        $teaching = $type === 'Teaching';

        $indicators = $teaching
            ? ['Contribute to program accreditation', 'Prepare and deliver assigned courses', 'Participate in university committees']
            : ['Improve office service turnaround', 'Process and maintain accurate office records', 'Participate in professional development'];

        $targets = $teaching
            ? ['Prepare evidence for one accreditation area', 'Update syllabi for all assigned courses', 'Participate in one committee']
            : ['Document one improved service procedure', 'Process 100 requests within service standards', 'Complete one relevant training'];

        $rows = [];
        foreach (RatingService::CATEGORIES as $index => $category) {
            $rows[] = [
                'category' => $category,
                'applicable' => true,
                'program' => $index === 0 ? ($teaching ? 'Higher Education Program' : 'Administrative Services') : '',
                'outcome' => '',
                'indicatorType' => $index === 0 ? 'Output indicator' : 'Function',
                'indicator' => $indicators[$index],
                'target' => $targets[$index],
                'actual' => '',
                'q' => '',
                'e' => '',
                't' => '',
                'remarks' => '',
            ];
        }

        return $rows;
    }

    /** Prototype model.js canEdit(). */
    public function canEdit(User $user, IpcrRecord $record): bool
    {
        return $user->isEmployee()
            && $record->owner_id === $user->id
            && in_array($record->status, ['Draft', 'Returned'], true);
    }

    /** Prototype model.js validateRecord(). @return list<string> */
    public function validateRecord(IpcrRecord $record, bool $submit = false): array
    {
        $errors = [];

        if (! trim($record->name) || ! trim($record->position) || ! $record->unit
            || ! $record->department || ! $record->period || ! trim($record->commitment)) {
            $errors[] = 'Complete the employee details, period, and commitment.';
        }

        foreach ($record->rows as $row) {
            if (! $row->applicable) {
                continue;
            }

            foreach (['q', 'e', 't'] as $key) {
                $value = $row->{$key};
                if ($value !== '' && $value !== null) {
                    $number = RatingService::numeric($value);
                    if ($number === null || $number < 1 || $number > 5) {
                        $errors[] = 'Q, E, and T must be numbers from 1 to 5.';
                        break;
                    }
                }
            }

            if ($submit && (! trim($row->indicator) || ! trim($row->target))) {
                $errors[] = 'Each applicable row needs an indicator and a target.';
            }
        }

        return array_values(array_unique($errors));
    }

    /** Prototype progress.js inScope(). */
    public function inScope(User $actor, User $person): bool
    {
        if (! $actor->active) {
            return false;
        }

        if ($actor->isEmployee()) {
            return $actor->id === $person->id;
        }

        if ($actor->role === 'Dean') {
            return $actor->unit === $person->unit;
        }

        if ($actor->role === 'Department Head') {
            return $actor->unit === $person->unit && $actor->department === $person->department;
        }

        return in_array($actor->role, ['HR Personnel', 'Vice President', 'President'], true);
    }

    /**
     * Records the actor may see, scoped by the OWNER's current assignment
     * (prototype visibleRecords() uses inScope(user, owner)). Administrators see none.
     */
    public function visibleQuery(User $user)
    {
        $query = IpcrRecord::query()->with(['rows', 'history', 'submissions']);

        if ($user->isEmployee()) {
            return $query->where('owner_id', $user->id);
        }

        if ($user->isAdmin()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role === 'Dean') {
            return $query->whereHas('owner', fn ($owner) => $owner->where('unit', $user->unit));
        }

        if ($user->role === 'Department Head') {
            return $query->whereHas('owner', fn ($owner) => $owner
                ->where('unit', $user->unit)
                ->where('department', $user->department));
        }

        if (in_array($user->role, ['HR Personnel', 'Vice President', 'President'], true)) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    /** Prototype model.js queue(): records exactly at the actor's review stage. */
    public function inReviewQueue(User $user, IpcrRecord $record): bool
    {
        if ($record->status !== $user->role || $user->isEmployee() || $user->isAdmin()) {
            return false;
        }

        $owner = $record->owner;

        return $owner !== null && $this->inScope($user, $owner);
    }

    /**
     * Prototype model.js transition(). Server-authoritative: throws IpcrException
     * with the prototype's exact messages.
     */
    public function transition(User $user, IpcrRecord $record, string $action, string $comment = ''): IpcrRecord
    {
        $at = self::nowIso();
        $comment = trim($comment);

        if ($action === 'submit') {
            if (! $this->canEdit($user, $record)) {
                throw new IpcrException('Only the owner can submit an editable record.');
            }

            $errors = $this->validateRecord($record, true);
            if ($errors !== []) {
                throw new IpcrException(implode(' ', $errors));
            }

            if (! in_array($record->period, $user->periodIds(), true)) {
                throw new IpcrException('You are not assigned to this evaluation period. Contact the administrator.');
            }

            $record->unit = $user->unit;
            $record->department = $user->department;
            $record->type = $user->type;

            $resubmit = $record->status === 'Returned';

            $settings = Setting::current();
            $record->rating_settings = ['weights' => $settings->weights, 'scale' => $settings->scale];

            $record->submissions()->create([
                'at' => $at,
                'snapshot' => [
                    'name' => $record->name,
                    'position' => $record->position,
                    'unit' => $record->unit,
                    'department' => $record->department,
                    'period' => $record->period,
                    'commitment' => $record->commitment,
                    'development' => $record->development,
                    'rateeDate' => $record->ratee_date ?? '',
                    'discussionDate' => $record->discussion_date ?? '',
                    'mfo' => $record->mfo ?? [],
                    'rows' => $record->rows->map->toApi()->values()->all(),
                    'ratingSettings' => $record->rating_settings,
                ],
            ]);

            $record->status = 'Department Head';
            $text = $resubmit ? 'Resubmitted' : 'Submitted';
        } else {
            if (! $this->inReviewQueue($user, $record)) {
                throw new IpcrException('This record is not assigned to your review queue.');
            }

            if ($action === 'return') {
                if (! in_array($user->role, self::RETURNABLE_ROLES, true)) {
                    throw new IpcrException('This role cannot return a record.');
                }
                if ($comment === '') {
                    throw new IpcrException('A correction comment is required.');
                }
                $record->status = 'Returned';
                $text = 'Returned for correction';
            } elseif ($action === 'approve') {
                $index = array_search($user->role, self::STAGES, true);
                $record->status = self::STAGES[$index + 1];
                if ($record->status === 'Completed') {
                    $record->final_rating = [
                        ...$this->rating->rating($record->rows, $record->rating_settings ?? Setting::current()->toArray()),
                        'approvedAt' => $at,
                    ];
                }
                $text = $record->status === 'Completed' ? 'Final approval · Completed' : 'Approved and forwarded';
            } else {
                throw new IpcrException('Unknown action.');
            }
        }

        $record->updated = $at;
        $record->save();

        $record->history()->create([
            'at' => $at,
            'actor' => $user->name,
            'role' => $user->role,
            'action' => $text,
            'comment' => $comment,
        ]);

        $this->notify($record, "{$record->name}: {$text}. {$comment}");

        return $record->fresh()->load(['rows', 'history', 'submissions']);
    }

    /** Prototype model.js notify(): owner plus every active reviewer at the record's current stage. */
    public function notify(IpcrRecord $record, string $text): void
    {
        $owner = $record->owner;
        $recipients = [$record->owner_id];

        User::query()->where('active', true)->where('role', $record->status)->get()
            ->each(function (User $reviewer) use ($record, $owner, &$recipients) {
                $matches = true;
                if ($record->status === 'Department Head') {
                    $matches = $reviewer->unit === $owner->unit && $reviewer->department === $owner->department;
                } elseif ($record->status === 'Dean') {
                    $matches = $reviewer->unit === $owner->unit;
                }
                if ($matches) {
                    $recipients[] = $reviewer->id;
                }
            });

        foreach (array_unique($recipients) as $userId) {
            Notification::create([
                'id' => (string) Str::uuid(),
                'user' => $userId,
                'text' => $text,
                'record' => $record->id,
                'at' => self::nowIso(),
                'read' => false,
            ]);
        }
    }
}
