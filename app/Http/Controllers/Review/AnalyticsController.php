<?php

namespace App\Http\Controllers\Review;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\Department;
use App\Models\RatingPeriod;
use App\Services\AnalyticsScope;
use App\Services\Audit;
use App\Services\IpcrAnalytics;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Analytics for the Department Head (own department) and the Dean/Director
 * (own college or office, optionally one department in it).
 */
class AnalyticsController extends Controller
{
    public function show(AnalyticsFilterRequest $request): View
    {
        $user = $request->user();
        $analytics = $this->analytics($request);
        $filters = $request->filters();

        $table = $analytics->employeeTable($filters['status'] ?? null, $filters['q'] ?? null, $filters['sort'] ?? 'name', $filters['dir'] ?? 'asc')
            ->paginate(20, pageName: 'page')->withQueryString()->fragment('employees');

        return view('review.analytics', [
            'analytics' => $analytics,
            'period' => $analytics->period,
            'summary' => $analytics->summary(),
            'filters' => $filters,
            'periods' => RatingPeriod::orderByDesc('starts_on')->get(),
            'departments' => $user->role === Role::Dean ? Department::where('college_id', $user->college_id)->orderBy('name')->get() : collect(),
            'isDean' => $user->role === Role::Dean,
            'ratings' => $analytics->ratingDistribution(),
            'sections' => $analytics->sectionAverages(),
            'lowest' => $analytics->lowestIndicators(),
            'trends' => $analytics->trends(),
            'performance' => $analytics->reviewPerformance($user->role),
            'byDepartment' => $user->role === Role::Dean && ! $analytics->scope->isDepartment() ? $analytics->groupedProgress('department') : collect(),
            'table' => $table,
            'notSubmitted' => $analytics->notSubmitted()->paginate(10, pageName: 'missing_page')->withQueryString()->fragment('not-submitted'),
            'daysToDeadline' => $analytics->daysToDeadline(),
        ]);
    }

    public function export(AnalyticsFilterRequest $request): StreamedResponse
    {
        $analytics = $this->analytics($request);
        $filters = $request->filters();
        $rows = $analytics->employeeTable($filters['status'] ?? null, $filters['q'] ?? null, $filters['sort'] ?? 'name', $filters['dir'] ?? 'asc');

        Audit::log('ipcr.exported', "{$request->user()->name} exported the analytics employee list for {$analytics->scope->label}, "
            .($analytics->period?->name ?? 'no period').' ('.(clone $rows)->count().' employees).', changes: ['filters' => $filters]);

        return $analytics->csv($rows, 'eipcr-analytics-'.Str::slug($analytics->scope->label).'-'.Str::slug($analytics->period?->name ?? 'none').'.csv');
    }

    private function analytics(AnalyticsFilterRequest $request): IpcrAnalytics
    {
        $scope = AnalyticsScope::forUser($request->user(), $request->intOrNull('college'), $request->intOrNull('department'));
        $period = $request->filled('period') ? RatingPeriod::findOrFail($request->integer('period')) : RatingPeriod::current();

        return new IpcrAnalytics($scope, $period, $request->validated('type'));
    }
}
