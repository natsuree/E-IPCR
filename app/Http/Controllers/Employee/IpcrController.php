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

        $template = $period ? IpcrTemplate::activeFor($period->id, $user->employee_type, $user) : null;

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
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $ipcr) {
            $locked = Ipcr::lockForUpdate()->findOrFail($ipcr->id);
            $this->authorize('update', $locked);
            foreach ($request->validated('items') ?? [] as $id => $values) {
                $locked->items()->whereKey($id)->update(\Illuminate\Support\Arr::only($values, ['actual_accomplishment', 'evidence_url']));
            }
            foreach ($request->validated('personal', []) as $id => $changes) {
                $item = $locked->items()->whereNotNull('employee_target_key')->find($id);
                abort_unless($item, 403);
                $item->update($changes);
            }
            if ($request->filled('new_target.indicator')) {
                $entry = $request->validated('new_target');
                $locked->items()->firstOrCreate(['employee_target_key' => $entry['key']], [
                    'section' => $entry['section'],
                    'program_outcome' => $entry['program_outcome'] ?? null,
                    'indicator' => $entry['indicator'],
                    'target' => $entry['target'],
                    'sort_order' => $locked->items()->where('section', $entry['section'])->max('sort_order') + 1,
                ]);
            }
            $locked->touch();
        });

        return match ($request->validated('action')) {
            'add_target' => redirect()->route('employee.ipcrs.edit', $ipcr)->with('status', 'Additional target saved.'),
            'print' => redirect()->route('employee.ipcrs.print', $ipcr),
            'submit' => redirect()->route('employee.ipcrs.confirm', $ipcr),
            default => redirect()->route('employee.ipcrs.edit', $ipcr)->with('status', 'Draft saved.'),
        };
    }

    public function confirm(Ipcr $ipcr): View|RedirectResponse
    {
        $this->authorize('update', $ipcr);
        $ipcr = $this->loadFull($ipcr);

        // Every empty accomplishment is reported against its own field (section and number as shown on the form).
        $missing = $this->workflow->missingAccomplishments($ipcr);
        if ($missing) {
            return redirect()->route('employee.ipcrs.edit', $ipcr)->withErrors([
                'ipcr' => 'Enter your actual accomplishment for every indicator before submitting ('.count($missing).' still empty). The empty fields are highlighted below; everything you entered was saved.',
            ] + $missing);
        }

        return view('employee.ipcrs.confirm', [
            'ipcr' => $ipcr,
            'firstStep' => \App\Enums\Role::DepartmentHead,
            'blocked' => $this->workflow->missingReviewer($ipcr, \App\Enums\Role::DepartmentHead),
        ]);
    }

    /** Remove a target the employee added (HR template rows are refused). */
    public function removeTarget(Request $request, Ipcr $ipcr, int $item): RedirectResponse
    {
        $this->authorize('update', $ipcr);
        $removed = $this->workflow->removeTarget($ipcr, $item, $request->user());

        return redirect()->route('employee.ipcrs.edit', $ipcr)
            ->with('status', 'Additional target "'.\Illuminate\Support\Str::limit($removed->indicator, 60).'" was removed.');
    }

    public function submit(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('update', $ipcr);
        try {
            $this->workflow->submit($ipcr, $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Back to the form, where every empty field is highlighted.
            return redirect()->route('employee.ipcrs.edit', $ipcr)->withErrors($e->errors());
        }

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
        return $ipcr->load(['user.college', 'user.department', 'period', 'template', 'items', 'reviews.user', 'retiredTargets', 'removedTargets']);
    }
}
