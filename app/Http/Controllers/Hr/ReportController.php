<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\College;
use App\Models\Department;
use App\Models\RatingPeriod;
use App\Services\AnalyticsScope;
use App\Services\Audit;
use App\Services\IpcrAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** University-wide Reports & Analytics, on the same service as the Department Head and Dean pages. */
class ReportController extends Controller
{
    public function index(AnalyticsFilterRequest $request): View|RedirectResponse
    {
        $analytics = $this->analytics($request);
        if (! $analytics->period) {
            return redirect()->route('hr.periods.index')->withErrors(['template' => 'Create a rating period first.']);
        }
        $filters = $request->filters();

        return view('hr.reports', [
            'analytics' => $analytics,
            'period' => $analytics->period,
            'summary' => $analytics->summary(),
            'filters' => $filters,
            'byCollege' => $analytics->groupedProgress('college'),
            'byDepartment' => $analytics->groupedProgress('department'),
            'ratings' => $analytics->ratingDistribution(),
            'trends' => $analytics->trends(),
            'notSubmitted' => $analytics->notSubmitted()->paginate(20, pageName: 'missing_page')->withQueryString()->fragment('not-submitted'),
            'table' => $analytics->employeeTable($filters['status'] ?? null, $filters['q'] ?? null, $filters['sort'] ?? 'name', $filters['dir'] ?? 'asc')
                ->paginate(20)->withQueryString()->fragment('employees'),
            'periods' => RatingPeriod::orderByDesc('starts_on')->get(),
            'colleges' => College::orderBy('name')->get(),
            'departments' => Department::with('college')->orderBy('name')->get(),
        ]);
    }

    /** CSV of the filtered employee list. */
    public function export(AnalyticsFilterRequest $request): StreamedResponse
    {
        $analytics = $this->analytics($request);
        abort_unless($analytics->period, 404);
        $filters = $request->filters();
        $rows = $analytics->employeeTable($filters['status'] ?? null, $filters['q'] ?? null, $filters['sort'] ?? 'name', $filters['dir'] ?? 'asc');

        Audit::log('ipcr.exported', "{$request->user()->name} exported the IPCR report for {$analytics->scope->label}, {$analytics->period->name} (".(clone $rows)->count().' employees).', changes: ['filters' => $filters]);

        return $analytics->csv($rows, 'eipcr-report-'.Str::slug($analytics->period->name).'.csv');
    }

    private function analytics(AnalyticsFilterRequest $request): IpcrAnalytics
    {
        $scope = AnalyticsScope::forUser($request->user(), $request->intOrNull('college'), $request->intOrNull('department'));
        $period = $request->filled('period') ? RatingPeriod::findOrFail($request->integer('period')) : RatingPeriod::current();

        return new IpcrAnalytics($scope, $period, $request->validated('type'));
    }
}
