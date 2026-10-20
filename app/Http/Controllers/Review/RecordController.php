<?php

namespace App\Http\Controllers\Review;

use App\Enums\IpcrStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\UnitFilterRequest;
use App\Models\College;
use App\Models\Department;
use App\Models\Ipcr;
use App\Models\RatingPeriod;
use App\Services\Audit;
use App\Services\IpcrDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Every completed IPCR, read-only, limited to the reviewer's scope in the query itself. */
class RecordController extends Controller
{
    public function index(UnitFilterRequest $request): View
    {
        $user = $request->user();
        $filters = $request->filters();

        // Scope fixes the filters the user is not allowed to widen.
        if ($user->role === Role::Dean) {
            $filters['college'] = $user->college_id;
        }
        if ($user->role === Role::DepartmentHead) {
            $filters['college'] = $user->college_id;
            $filters['department'] = $user->department_id;
        }

        $base = Ipcr::visibleTo($user)
            ->where('ipcrs.status', IpcrStatus::Completed->value)
            ->when($filters['period'] ?? null, fn ($q, $id) => $q->where('ipcrs.rating_period_id', $id))
            ->when($filters['college'] ?? null, fn ($q, $id) => $q->whereHas('user', fn ($u) => $u->where('college_id', $id)))
            ->when($filters['department'] ?? null, fn ($q, $id) => $q->whereHas('user', fn ($u) => $u->where('department_id', $id)))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('employee_id', 'like', "%{$term}%"))));

        $summary = [
            'records' => (clone $base)->count(),
            'periods' => (clone $base)->distinct()->count('ipcrs.rating_period_id'),
            'average' => (clone $base)->avg('ipcrs.final_rating'),
        ];

        $records = $base
            ->with(['user.college', 'user.department', 'period'])
            ->join('rating_periods', 'rating_periods.id', '=', 'ipcrs.rating_period_id')
            ->orderByDesc('rating_periods.starts_on')
            ->orderByDesc('ipcrs.completed_at')
            ->select('ipcrs.*')
            ->paginate(20)
            ->withQueryString();

        return view('review.records', [
            'records' => $records,
            'summary' => $summary,
            'filters' => $filters,
            'periods' => RatingPeriod::orderByDesc('starts_on')->get(),
            'colleges' => $user->role === Role::Hr ? College::orderBy('name')->get() : College::whereKey($user->college_id)->get(),
            'departments' => match ($user->role) {
                Role::Hr => Department::with('college')->orderBy('name')->get(),
                Role::Dean => Department::with('college')->where('college_id', $user->college_id)->orderBy('name')->get(),
                default => Department::with('college')->whereKey($user->department_id)->get(),
            },
            'lockCollege' => $user->role !== Role::Hr,
            'lockDepartment' => $user->role === Role::DepartmentHead,
        ]);
    }

    public function show(Ipcr $ipcr): View
    {
        $this->authorize('viewRecord', $ipcr);

        return view('review.record', [
            'ipcr' => $ipcr->load(['user.college', 'user.department', 'period', 'template', 'items', 'reviews.user']),
        ]);
    }

    public function print(Request $request, Ipcr $ipcr): View
    {
        $this->authorize('viewRecord', $ipcr);

        return view('ipcr.print', [
            'ipcr' => $ipcr->load(['user.college', 'user.department', 'period', 'template', 'items', 'reviews.user']),
            'back' => route($request->user()->routePrefix().'.records.show', $ipcr),
        ]);
    }

    public function pdf(Request $request, Ipcr $ipcr, IpcrDocument $document): Response
    {
        $this->authorize('viewRecord', $ipcr);
        Audit::log('ipcr.downloaded', "{$request->user()->name} downloaded the PDF of {$ipcr->user->name}'s IPCR for {$ipcr->period->name}.", $ipcr);

        return $document->download($ipcr);
    }
}
