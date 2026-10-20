<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Ipcr;
use App\Models\RatingPeriod;
use App\Models\User;
use App\Services\AnalyticsScope;
use App\Services\IpcrAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $period = RatingPeriod::current();

        return match ($user->role) {
            Role::Teaching, Role::NonTeaching => $this->employee($user, $period),
            Role::DepartmentHead, Role::Dean => $this->reviewer($user, $period),
            Role::Hr => $this->hr($user, $period),
            Role::Admin => $this->admin(),
        };
    }

    private function employee(User $user, ?RatingPeriod $period): View
    {
        $ipcr = $period ? $user->ipcrs()->where('rating_period_id', $period->id)->first() : null;

        return view('employee.dashboard', [
            'period' => $period,
            'openPeriod' => RatingPeriod::open(),
            'ipcr' => $ipcr,
            'lastAction' => $ipcr?->reviews()->with('user')->reorder()->latest('id')->first(),
            'history' => $user->ipcrs()->with('period')->latest('id')->take(5)->get(),
        ]);
    }

    private function reviewer(User $user, ?RatingPeriod $period): View
    {
        return view('review.dashboard', [
            ...$this->scoped($user, $period),
            'waiting' => Ipcr::awaiting($user)->count(),
        ]);
    }

    private function hr(User $user, ?RatingPeriod $period): View
    {
        $data = $this->scoped($user, $period);

        return view('hr.dashboard', [
            ...$data,
            'byCollege' => $data['analytics']?->groupedProgress('college') ?? collect(),
            'waiting' => Ipcr::awaiting($user)->count(),
        ]);
    }

    /** Summary cards and the past-deadline list, from the shared analytics service. */
    private function scoped(User $user, ?RatingPeriod $period): array
    {
        $analytics = $period ? new IpcrAnalytics(AnalyticsScope::forUser($user), $period) : null;
        $summary = $analytics?->summary();

        return [
            'period' => $period,
            'analytics' => $analytics,
            'summary' => $summary,
            'overdue' => $period?->deadlinePassed() ? $analytics->notSubmitted()->limit(15)->get() : collect(),
            'overdueCount' => $period?->deadlinePassed() ? $summary['not_submitted'] : 0,
            'scopeLabel' => $analytics?->scope->label ?? $user->unitLabel(),
        ];
    }

    private function admin(): View
    {
        return view('admin.dashboard', [
            'counts' => [
                'accounts' => User::count(),
                'active' => User::where('active', true)->count(),
                'inactive' => User::where('active', false)->count(),
                'units' => College::where('active', true)->count(),
            ],
            'byRole' => User::where('active', true)->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
            'recent' => AuditLog::with('user')->latest('id')->take(10)->get(),
        ]);
    }
}
