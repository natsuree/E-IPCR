<?php

namespace App\Http\Controllers\Review;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\ReviewDecisionRequest;
use App\Http\Requests\UnitFilterRequest;
use App\Models\College;
use App\Models\Department;
use App\Models\Ipcr;
use App\Services\FinalRating;
use App\Services\IpcrWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueueController extends Controller
{
    public function __construct(private IpcrWorkflow $workflow) {}

    public function index(UnitFilterRequest $request): View
    {
        $user = $request->user();
        $filters = $request->filters();
        $isHr = $user->role === Role::Hr;

        $query = Ipcr::awaiting($user)
            ->with(['user.college', 'user.department', 'period'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('employee_id', 'like', "%{$term}%"))))
            ->when($filters['college'] ?? null, fn ($q, $id) => $q->whereHas('user', fn ($u) => $u->where('college_id', $id)))
            ->when($filters['department'] ?? null, fn ($q, $id) => $q->whereHas('user', fn ($u) => $u->where('department_id', $id)));

        if ($isHr) {
            // HR: grouped by college/office, then department, then employee name.
            $query->join('users', 'users.id', '=', 'ipcrs.user_id')
                ->leftJoin('colleges', 'colleges.id', '=', 'users.college_id')
                ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
                ->orderBy('colleges.name')->orderBy('departments.name')->orderBy('users.name')
                ->select('ipcrs.*');
        } else {
            $query->orderBy('submitted_at');
        }

        $ipcrs = $query->paginate(20)->withQueryString();

        $grouped = $isHr
            ? $ipcrs->getCollection()
                ->groupBy(fn (Ipcr $i) => $i->user->college?->name ?? 'Unassigned')
                ->map(fn ($group) => $group->groupBy(fn (Ipcr $i) => $i->user->department?->name ?? 'Office staff'))
            : null;

        return view('review.queue', [
            'ipcrs' => $ipcrs,
            'grouped' => $grouped,
            'filters' => $filters,
            'colleges' => $isHr ? College::orderBy('name')->get() : collect(),
            'departments' => match ($user->role) {
                Role::Hr => Department::with('college')->orderBy('name')->get(),
                Role::Dean => Department::where('college_id', $user->college_id)->orderBy('name')->get(),
                default => collect(),
            },
        ]);
    }

    public function show(Request $request, Ipcr $ipcr): View
    {
        $this->authorize('review', $ipcr);

        $ipcr->load(['user.college', 'user.department', 'period', 'template', 'items', 'reviews.user']);

        return view('review.review', [
            'ipcr' => $ipcr,
            'isHr' => $request->user()->role === Role::Hr,
            'preview' => FinalRating::compute($ipcr->items),
        ]);
    }

    public function decide(ReviewDecisionRequest $request, Ipcr $ipcr): RedirectResponse
    {
        $user = $request->user();

        if ($request->isApproval()) {
            $this->workflow->approve($ipcr, $user, $request->ratings(), $request->validated('remarks'));
            $message = $user->role === Role::Hr
                ? 'Final approval recorded. '.$ipcr->user->name.'\'s IPCR is completed with a final rating of '.number_format((float) $ipcr->fresh()->final_rating, 2).'.'
                : $ipcr->user->name.'\'s IPCR was approved and forwarded.';
        } else {
            $this->workflow->return($ipcr, $user, $request->ratings(), $request->validated('remarks'));
            $message = $ipcr->user->name.'\'s IPCR was returned for correction.';
        }

        return redirect()->route($user->routePrefix().'.queue')->with('status', $message);
    }
}
