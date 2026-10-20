<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\College;
use App\Models\Department;
use App\Models\Ipcr;
use App\Models\RatingPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * IPCR analytics for one scope (HR: everything or one unit; Dean/Director: their
 * college; Department Head: their department), one rating period and an optional
 * employee type. Every number comes from grouped SQL queries, filtered by the scope
 * in the query itself.
 *
 * "Employees" = active Teaching and Non-Teaching accounts in the scope whose
 * account existed by the end of the rating period.
 */
class IpcrAnalytics
{
    public const STATUS_LABELS = [
        'not_started' => 'Not started',
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'returned' => 'Returned',
        'with_dean' => 'With Dean/Director',
        'with_hr' => 'With HR',
        'completed' => 'Completed',
    ];

    /** Statuses meaning the employee has handed the IPCR in and it is not back with them. */
    public const SUBMITTED = ['submitted', 'with_dean', 'with_hr', 'completed'];

    public const IN_REVIEW = ['with_dean', 'with_hr'];

    public const SORTS = [
        'name' => 'users.name',
        'department' => 'departments.name',
        'status' => 'ipcrs.status',
        'submitted' => 'ipcrs.submitted_at',
        'rating' => 'ipcrs.final_rating',
    ];

    private ?array $statusCounts = null;

    public function __construct(
        public readonly AnalyticsScope $scope,
        public readonly ?RatingPeriod $period,
        public readonly ?string $employeeType = null,
    ) {}

    // ----------------------------------------------------------------- base queries

    /** Employee constraints on a query that has the users table available. */
    private function constrain(QueryBuilder|Builder $query, ?RatingPeriod $period = null, bool $activeOnly = true): QueryBuilder|Builder
    {
        $period ??= $this->period;

        $query->whereIn('users.role', [Role::Teaching->value, Role::NonTeaching->value])
            ->whereNull('users.deleted_at')
            ->when($activeOnly, fn ($q) => $q->where('users.active', true))
            ->when($this->employeeType, fn ($q) => $q->where('users.employee_type', $this->employeeType))
            ->when($period, fn ($q) => $q->where('users.created_at', '<=', $period->ends_on->copy()->endOfDay()));

        return $this->scope->apply($query);
    }

    /** Employees in scope (Eloquent, so rows can be shown as users). */
    public function employees(): Builder
    {
        return $this->constrain(User::query());
    }

    /** Employees left-joined to their IPCR for the period (one row per employee). */
    private function withIpcr(Builder|QueryBuilder $query): Builder|QueryBuilder
    {
        return $query->leftJoin('ipcrs', function ($join) {
            $join->on('ipcrs.user_id', '=', 'users.id')
                ->where('ipcrs.rating_period_id', $this->period?->id ?? 0)
                ->whereNull('ipcrs.deleted_at');
        });
    }

    /** Completed IPCRs for the period whose employee is in scope. */
    private function completed(?RatingPeriod $period = null): Builder
    {
        $period ??= $this->period;

        return $this->constrain(
            Ipcr::query()->join('users', 'users.id', '=', 'ipcrs.user_id')
                ->where('ipcrs.status', 'completed')
                ->where('ipcrs.rating_period_id', $period?->id ?? 0),
            $period,
        );
    }

    // ----------------------------------------------------------------- summary

    /** @return array<string, int> every status key with its count of employees */
    public function statusCounts(): array
    {
        if ($this->statusCounts !== null) {
            return $this->statusCounts;
        }

        $counts = array_fill_keys(array_keys(self::STATUS_LABELS), 0);

        $rows = $this->withIpcr($this->employees()->toBase())
            ->selectRaw("coalesce(ipcrs.status, 'not_started') as status_key, count(*) as total")
            ->groupBy('status_key')
            ->pluck('total', 'status_key');

        foreach ($rows as $status => $total) {
            $counts[$status] = (int) $total;
        }

        return $this->statusCounts = $counts;
    }

    public function summary(): array
    {
        $c = $this->statusCounts();
        $employees = array_sum($c);
        $sum = fn (array $keys) => array_sum(array_intersect_key($c, array_flip($keys)));
        $average = $this->period ? $this->completed()->avg('ipcrs.final_rating') : null;

        return [
            'employees' => $employees,
            'submitted' => $sum(self::SUBMITTED),
            'completed' => $c['completed'],
            'returned' => $c['returned'],
            'in_review' => $sum(self::IN_REVIEW),
            'passed_head' => $sum(['with_dean', 'with_hr', 'completed']),
            'not_submitted' => $c['not_started'] + $c['draft'],
            'average' => $average === null ? null : round((float) $average, 2),
            'adjectival' => $average === null ? null : FinalRating::adjectival(round((float) $average, 2)),
            'counts' => $c,
        ];
    }

    public function percent(int $part): int
    {
        $total = array_sum($this->statusCounts());

        return $total === 0 ? 0 : (int) round($part / $total * 100);
    }

    /** Days until the submission deadline (negative = overdue), or null when there is none. */
    public function daysToDeadline(): ?int
    {
        if (! $this->period?->submission_deadline) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->period->submission_deadline->copy()->startOfDay(), false);
    }

    // ----------------------------------------------------------------- ratings

    /** @return array<string, int> adjectival rating => completed IPCRs */
    public function ratingDistribution(): array
    {
        $counts = array_fill_keys([...array_keys(FinalRating::settings()['bands']), FinalRating::LOWEST_BAND], 0);

        $rows = $this->completed()->toBase()
            ->selectRaw('ipcrs.adjectival_rating as label, count(*) as total')
            ->groupBy('ipcrs.adjectival_rating')
            ->pluck('total', 'label');

        foreach ($rows as $label => $total) {
            $counts[$label] = (int) $total;
        }

        return $counts;
    }

    private function ratedItems(): QueryBuilder
    {
        return $this->constrain(
            DB::table('ipcr_items')
                ->join('ipcrs', 'ipcrs.id', '=', 'ipcr_items.ipcr_id')
                ->join('users', 'users.id', '=', 'ipcrs.user_id')
                ->whereNull('ipcrs.deleted_at')
                ->where('ipcrs.status', 'completed')
                ->where('ipcrs.rating_period_id', $this->period?->id ?? 0)
                ->whereNotNull('ipcr_items.rating'),
        );
    }

    /** @return array<string, float|null> section => average indicator rating */
    public function sectionAverages(): array
    {
        $rows = $this->ratedItems()
            ->selectRaw('ipcr_items.section as section, avg(ipcr_items.rating) as average')
            ->groupBy('ipcr_items.section')
            ->pluck('average', 'section');

        $averages = [];
        foreach (array_keys(Ipcr::SECTIONS) as $section) {
            $averages[$section] = isset($rows[$section]) ? round((float) $rows[$section], 2) : null;
        }

        return $averages;
    }

    /** The indicators with the lowest average rating. */
    public function lowestIndicators(int $limit = 5): Collection
    {
        return $this->ratedItems()
            ->selectRaw('ipcr_items.section as section, ipcr_items.indicator as indicator, avg(ipcr_items.rating) as average, count(*) as ratings')
            ->groupBy('ipcr_items.section', 'ipcr_items.indicator')
            ->orderBy('average')
            ->orderBy('ipcr_items.indicator')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'section' => Ipcr::SECTIONS[$row->section] ?? $row->section,
                'indicator' => $row->indicator,
                'average' => round((float) $row->average, 2),
                'ratings' => (int) $row->ratings,
            ]);
    }

    // ----------------------------------------------------------------- trends

    /** Average final rating and completion rate for the last $max periods that have IPCRs in scope. */
    public function trends(int $max = 6): Collection
    {
        // Periods that have any IPCR from an employee in scope.
        $periodIds = $this->scope->apply(
            Ipcr::query()->join('users', 'users.id', '=', 'ipcrs.user_id')
                ->whereIn('users.role', [Role::Teaching->value, Role::NonTeaching->value])
                ->when($this->employeeType, fn ($q) => $q->where('users.employee_type', $this->employeeType))
        )->toBase()->distinct()->pluck('ipcrs.rating_period_id');

        $periods = RatingPeriod::whereIn('id', $periodIds)
            ->orderByDesc('starts_on')->take($max)->get()->sortBy('starts_on')->values();

        $completed = $this->scope->apply(
            Ipcr::query()->join('users', 'users.id', '=', 'ipcrs.user_id')
                ->whereIn('users.role', [Role::Teaching->value, Role::NonTeaching->value])
                ->whereNull('users.deleted_at')
                ->where('users.active', true)
                ->when($this->employeeType, fn ($q) => $q->where('users.employee_type', $this->employeeType))
                ->where('ipcrs.status', 'completed')
                ->whereIn('ipcrs.rating_period_id', $periods->pluck('id'))
        )->toBase()
            ->selectRaw('ipcrs.rating_period_id as period_id, count(*) as total, avg(ipcrs.final_rating) as average')
            ->groupBy('ipcrs.rating_period_id')
            ->get()
            ->keyBy('period_id');

        return $periods->map(function (RatingPeriod $period) use ($completed) {
            $employees = $this->constrain(User::query()->toBase(), $period)->count();
            $row = $completed->get($period->id);
            $done = (int) ($row->total ?? 0);

            return [
                'period' => $period->name,
                'employees' => $employees,
                'completed' => $done,
                'completion_rate' => $employees ? round($done / $employees * 100, 1) : 0.0,
                'average' => $row ? round((float) $row->average, 2) : null,
            ];
        });
    }

    // ----------------------------------------------------------------- breakdowns

    /**
     * Status counts, completion and average rating per unit.
     *
     * @param  'college'|'department'  $by
     */
    public function groupedProgress(string $by): Collection
    {
        $column = $by === 'college' ? 'users.college_id' : 'users.department_id';

        $counts = $this->withIpcr($this->employees()->toBase())
            ->selectRaw("{$column} as unit_id, coalesce(ipcrs.status, 'not_started') as status_key, count(*) as total")
            ->groupBy('unit_id', 'status_key')
            ->get()
            ->groupBy('unit_id');

        $averages = $this->period
            ? $this->completed()->toBase()->selectRaw("{$column} as unit_id, avg(ipcrs.final_rating) as average")->groupBy('unit_id')->pluck('average', 'unit_id')
            : collect();

        $names = $by === 'college'
            ? College::whereIn('id', $counts->keys()->filter())->pluck('name', 'id')
            : Department::with('college')->whereIn('id', $counts->keys()->filter())->get()->keyBy('id');

        return $counts->map(function (Collection $rows, $unitId) use ($names, $averages, $by) {
            $c = array_fill_keys(array_keys(self::STATUS_LABELS), 0);
            foreach ($rows as $row) {
                $c[$row->status_key] = (int) $row->total;
            }
            $employees = array_sum($c);
            $submitted = array_sum(array_intersect_key($c, array_flip(self::SUBMITTED)));
            $average = $averages[$unitId] ?? null;

            if ($by === 'college') {
                $name = $names[$unitId] ?? 'Unassigned';
                $college = $name;
            } else {
                $department = $names[$unitId] ?? null;
                $name = $department?->name ?? 'No department (college or office staff)';
                $college = $department?->college?->name;
            }

            return [
                'id' => $unitId ? (int) $unitId : null,
                'name' => $name,
                'college' => $college,
                'employees' => $employees,
                'counts' => $c,
                'submitted' => $submitted,
                'in_review' => $c['with_dean'] + $c['with_hr'],
                'completed' => $c['completed'],
                'returned' => $c['returned'],
                'not_submitted' => $c['not_started'] + $c['draft'],
                'submitted_pct' => $employees ? (int) round($submitted / $employees * 100) : 0,
                'completed_pct' => $employees ? (int) round($c['completed'] / $employees * 100) : 0,
                'average' => $average === null ? null : round((float) $average, 2),
            ];
        })->sortBy(fn ($row) => ($row['college'] ?? '').'|'.$row['name'])->values();
    }

    /**
     * One row per employee with their IPCR for the period. Narrowed by status and
     * search (the summary cards are not), sorted by a whitelisted column.
     */
    public function employeeTable(?string $status = null, ?string $search = null, string $sort = 'name', string $direction = 'asc'): Builder
    {
        $query = $this->withIpcr($this->employees())
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->leftJoin('colleges', 'colleges.id', '=', 'users.college_id')
            ->select('users.*')
            ->addSelect([
                'ipcrs.id as ipcr_id', 'ipcrs.status as ipcr_status', 'ipcrs.submitted_at as ipcr_submitted_at',
                'ipcrs.final_rating as ipcr_final_rating', 'ipcrs.adjectival_rating as ipcr_adjectival_rating',
                'departments.name as department_name', 'colleges.name as college_name',
            ]);

        match ($status) {
            null, '' => null,
            'not_started' => $query->whereNull('ipcrs.id'),
            'in_review' => $query->whereIn('ipcrs.status', self::IN_REVIEW),
            'not_submitted' => $query->where(fn ($q) => $q->whereNull('ipcrs.id')->orWhere('ipcrs.status', 'draft')),
            default => $query->where('ipcrs.status', $status),
        };

        if ($search) {
            $query->where(fn ($q) => $q->where('users.name', 'like', "%{$search}%")->orWhere('users.employee_id', 'like', "%{$search}%"));
        }

        $column = self::SORTS[$sort] ?? self::SORTS['name'];
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($column, $direction)->orderBy('users.name');
    }

    /** Employees with no submitted IPCR for the period (not started or still a draft). */
    public function notSubmitted(): Builder
    {
        return $this->employeeTable('not_submitted');
    }

    // ----------------------------------------------------------------- review performance

    /** How the reviewer's own step is doing: waiting now, time taken, approvals and returns in the period. */
    public function reviewPerformance(Role $role): array
    {
        $status = $role->reviewStatus()?->value;

        $waitingBase = $this->scope->apply(
            Ipcr::query()->join('users', 'users.id', '=', 'ipcrs.user_id')
                ->where('ipcrs.status', $status)
                ->when($this->employeeType, fn ($q) => $q->where('users.employee_type', $this->employeeType))
        );
        $waiting = (clone $waitingBase)->count();

        $entered = DB::table('ipcr_reviews')->selectRaw('ipcr_id, max(created_at) as entered_at')->groupBy('ipcr_id');
        $oldest = (clone $waitingBase)->toBase()->joinSub($entered, 'last_action', 'last_action.ipcr_id', '=', 'ipcrs.id')->min('last_action.entered_at');

        $decisions = $this->scope->apply(
            DB::table('ipcr_reviews as r')
                ->join('ipcrs', 'ipcrs.id', '=', 'r.ipcr_id')
                ->join('users', 'users.id', '=', 'ipcrs.user_id')
                ->whereNull('ipcrs.deleted_at')
                ->where('r.role', $role->value)
                ->whereIn('r.action', ['approved', 'returned', 'completed'])
                ->where('ipcrs.rating_period_id', $this->period?->id ?? 0)
                ->when($this->employeeType, fn ($q) => $q->where('users.employee_type', $this->employeeType))
        )
            ->select('r.id', 'r.action', 'r.created_at', 'r.remarks', 'users.name as employee')
            ->selectSub(
                DB::table('ipcr_reviews as p')->selectRaw('max(p.created_at)')->whereColumn('p.ipcr_id', 'r.ipcr_id')->whereColumn('p.id', '<', 'r.id'),
                'previous_at',
            )
            ->get();

        $waits = $decisions->filter(fn ($d) => $d->previous_at)
            ->map(fn ($d) => Carbon::parse($d->previous_at)->diffInSeconds(Carbon::parse($d->created_at)) / 86400);

        return [
            'waiting' => $waiting,
            'oldest_days' => $oldest ? (int) floor(Carbon::parse($oldest)->diffInSeconds(now()) / 86400) : null,
            'average_days' => $waits->isEmpty() ? null : round($waits->avg(), 1),
            'approved' => $decisions->whereIn('action', ['approved', 'completed'])->count(),
            'returned' => $decisions->where('action', 'returned')->count(),
            'return_remarks' => $decisions->where('action', 'returned')->sortByDesc('created_at')->take(10)->values(),
        ];
    }

    // ----------------------------------------------------------------- export

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? 'not_started'] ?? ucfirst((string) $status);
    }

    /** Who holds the IPCR now. */
    public static function currentReviewer(?string $status): string
    {
        return match ($status) {
            'submitted' => 'Department Head',
            'with_dean' => 'Dean/Director',
            'with_hr' => 'HR',
            'draft', 'returned' => 'Employee',
            default => '—',
        };
    }

    /** CSV of the employee table (same scope, filters and sort as the page). */
    public function csv(Builder $rows, string $filename): StreamedResponse
    {
        $period = $this->period;

        return response()->streamDownload(function () use ($rows, $period) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads the en dash and accents correctly
            fputcsv($out, ['Employee', 'Employee ID', 'Type', 'College / office', 'Department', 'Rating period', 'Status', 'Current reviewer', 'Submitted on', 'Final rating', 'Adjectival rating']);
            foreach ($rows->cursor() as $user) {
                fputcsv($out, [
                    $user->name,
                    $user->employee_id ?? '',
                    $user->employeeTypeLabel(),
                    $user->college_name ?? '',
                    $user->department_name ?? '',
                    $period?->name ?? '',
                    self::statusLabel($user->ipcr_status),
                    self::currentReviewer($user->ipcr_status),
                    $user->ipcr_submitted_at ? Carbon::parse($user->ipcr_submitted_at)->format('Y-m-d') : '',
                    $user->ipcr_final_rating !== null ? number_format((float) $user->ipcr_final_rating, 2) : '',
                    $user->ipcr_adjectival_rating ?? '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
