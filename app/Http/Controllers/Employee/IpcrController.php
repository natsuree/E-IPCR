<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\UpdateIpcrRequest;
use App\Models\Ipcr;
use App\Models\IpcrTemplate;
use App\Models\RatingPeriod;
use App\Services\Audit;
use App\Services\IpcrDocument;
use App\Services\IpcrWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class IpcrController extends Controller
{
    public function __construct(private IpcrWorkflow $workflow) {}

    public function index(Request $request): View
    {
        return view('employee.ipcrs.index', [
            'ipcrs' => $request->user()->ipcrs()->with(['period', 'template'])
                ->join('rating_periods', 'rating_periods.id', '=', 'ipcrs.rating_period_id')
                ->orderByDesc('rating_periods.starts_on')
                ->select('ipcrs.*')
                ->paginate(15),
            'period' => RatingPeriod::current(),
        ]);
    }

    /** "Create / Edit IPCR": open this period's IPCR, or offer to start one. */
    public function current(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $period = RatingPeriod::open();
        $ipcr = $period ? $user->ipcrs()->where('rating_period_id', $period->id)->first() : null;

        if (! $ipcr && ! $period) {
            // No open period: continue a returned or draft IPCR from an earlier period, if any.
            $ipcr = $user->ipcrs()->whereIn('status', ['draft', 'returned'])->latest('id')->first();
        }

        if ($ipcr) {
            return $ipcr->status->isEditableByEmployee()
                ? redirect()->route('employee.ipcrs.edit', $ipcr)
                : redirect()->route('employee.ipcrs.show', $ipcr);
        }

        $template = $period ? IpcrTemplate::activeFor($period->id, $user->employee_type) : null;

        return view('employee.ipcrs.start', [
            'period' => $period,
            'template' => $template?->load('items'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ipcr = $this->workflow->create($request->user());

        return redirect()->route('employee.ipcrs.edit', $ipcr)
            ->with('status', 'Your IPCR for '.$ipcr->period->name.' was created as a draft.');
    }

    public function show(Ipcr $ipcr): View
    {
        $this->authorize('view', $ipcr);

        return view('employee.ipcrs.show', ['ipcr' => $this->loadFull($ipcr)]);
    }

    public function edit(Request $request, Ipcr $ipcr): View|RedirectResponse
    {
        $this->authorize('view', $ipcr);

        if ($request->user()->cannot('update', $ipcr)) {
            return redirect()->route('employee.ipcrs.show', $ipcr)
                ->with('status', 'This IPCR is with the reviewers and cannot be edited now.');
        }

        return view('employee.ipcrs.edit', [
            'ipcr' => $this->loadFull($ipcr),
            'returned' => $ipcr->status->value === 'returned' ? $ipcr->latestReturn()?->load('user') : null,
        ]);
    }

    public function update(UpdateIpcrRequest $request, Ipcr $ipcr): RedirectResponse
    {
        $itemIds = $ipcr->items()->pluck('id')->all();

        foreach ($request->validated('items') ?? [] as $id => $values) {
            if (in_array((int) $id, $itemIds, true)) {
                $ipcr->items()->whereKey($id)->update(['actual_accomplishment' => $values['actual_accomplishment'] ?? null]);
            }
        }
        $ipcr->touch();

        return match ($request->validated('action')) {
            'print' => redirect()->route('employee.ipcrs.print', $ipcr),
            'submit' => redirect()->route('employee.ipcrs.confirm', $ipcr),
            default => redirect()->route('employee.ipcrs.edit', $ipcr)->with('status', 'Draft saved.'),
        };
    }

    public function confirm(Ipcr $ipcr): View|RedirectResponse
    {
        $this->authorize('update', $ipcr);
        $ipcr = $this->loadFull($ipcr);

        $missing = $ipcr->items->filter(fn ($i) => trim((string) $i->actual_accomplishment) === '');
        if ($missing->isNotEmpty()) {
            return redirect()->route('employee.ipcrs.edit', $ipcr)->withErrors([
                'ipcr' => 'Enter your actual accomplishment for every indicator before submitting ('.$missing->count().' still empty).',
            ]);
        }

        return view('employee.ipcrs.confirm', [
            'ipcr' => $ipcr,
            'firstStep' => $this->workflow->nextReviewer($ipcr, null)[0],
        ]);
    }

    public function submit(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('update', $ipcr);
        $this->workflow->submit($ipcr, $request->user());

        return redirect()->route('employee.tracking', ['ipcr' => $ipcr->id])
            ->with('status', 'Your IPCR was submitted.');
    }

    public function print(Ipcr $ipcr): View
    {
        $this->authorize('view', $ipcr);

        return view('ipcr.print', ['ipcr' => $this->loadFull($ipcr), 'back' => route('employee.ipcrs.show', $ipcr)]);
    }

    public function pdf(Request $request, Ipcr $ipcr, IpcrDocument $document): Response
    {
        $this->authorize('view', $ipcr);
        Audit::log('ipcr.downloaded', "{$request->user()->name} downloaded the PDF of their IPCR for {$ipcr->period->name}.", $ipcr);

        return $document->download($ipcr);
    }

    public function tracking(Request $request): View
    {
        $user = $request->user();
        $ipcrs = $user->ipcrs()->with('period')->latest('id')->get();

        $ipcr = $request->filled('ipcr')
            ? $ipcrs->firstWhere('id', (int) $request->query('ipcr'))
            : ($ipcrs->firstWhere('rating_period_id', RatingPeriod::current()?->id) ?? $ipcrs->first());

        // Another user's IPCR ID in the URL is refused, not silently ignored.
        abort_if($request->filled('ipcr') && ! $ipcr, 403, 'You can only track your own IPCRs.');

        return view('employee.tracking', [
            'ipcr' => $ipcr ? $this->loadFull($ipcr) : null,
            'ipcrs' => $ipcrs,
            'returned' => $ipcr?->status->value === 'returned' ? $ipcr->latestReturn()?->load('user') : null,
        ]);
    }

    private function loadFull(Ipcr $ipcr): Ipcr
    {
        return $ipcr->load(['user.college', 'user.department', 'period', 'template', 'items', 'reviews.user']);
    }
}
