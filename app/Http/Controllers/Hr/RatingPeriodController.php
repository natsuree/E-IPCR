<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RatingPeriodRequest;
use App\Models\RatingPeriod;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** HR creates rating periods, sets the deadline, and opens and closes them. Only one is open at a time. */
class RatingPeriodController extends Controller
{
    public function index(Request $request): View
    {
        return view('hr.periods.index', [
            'periods' => RatingPeriod::withCount([
                'ipcrs',
                'ipcrs as completed_count' => fn ($q) => $q->where('status', 'completed'),
            ])->orderByDesc('starts_on')->paginate(15),
            'editing' => $request->filled('edit') ? RatingPeriod::find($request->integer('edit')) : null,
        ]);
    }

    public function store(RatingPeriodRequest $request): RedirectResponse
    {
        $period = RatingPeriod::create([...$request->validated(), 'status' => 'draft']);
        Audit::log('period.created', "{$request->user()->name} created the rating period {$period->name} (deadline {$period->submission_deadline->format('M j, Y')}).", $period, $request->validated());

        return redirect()->route('hr.periods.index')->with('status', "{$period->name} was created. Publish its templates in IPCR Templates, then open it.");
    }

    public function update(RatingPeriodRequest $request, RatingPeriod $period): RedirectResponse
    {
        $period->fill($request->validated());
        $changes = $period->getDirty();
        $period->save();

        if ($changes) {
            Audit::log('period.updated', "{$request->user()->name} updated the rating period {$period->name}.", $period, $changes);
        }

        return redirect()->route('hr.periods.index')->with('status', "{$period->name} was saved.");
    }

    public function open(Request $request, RatingPeriod $period): RedirectResponse
    {
        DB::transaction(function () use ($period) {
            $other = RatingPeriod::where('status', 'open')->whereKeyNot($period->id)->lockForUpdate()->first();
            if ($other) {
                throw \Illuminate\Validation\ValidationException::withMessages(['template' => "{$other->name} is still open. Close it before opening {$period->name}."]);
            }
            $period->update(['status' => 'open', 'opened_at' => now(), 'closed_at' => null]);
        });

        Audit::log('period.opened', "{$request->user()->name} opened the rating period {$period->name}.", $period);

        return back()->with('status', "{$period->name} is open. Employees can now create IPCRs for it.");
    }

    public function close(Request $request, RatingPeriod $period): RedirectResponse
    {
        if (! $period->isOpen()) {
            return back()->withErrors(['template' => "{$period->name} is not open."]);
        }

        $period->update(['status' => 'closed', 'closed_at' => now()]);
        Audit::log('period.closed', "{$request->user()->name} closed the rating period {$period->name}.", $period);

        return back()->with('status', "{$period->name} is closed. No new IPCRs can be created for it; IPCRs already started can still be finished and reviewed.");
    }
}
