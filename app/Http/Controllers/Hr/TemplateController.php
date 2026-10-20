<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreTemplateRequest;
use App\Http\Requests\Hr\TemplateFilterRequest;
use App\Http\Requests\Hr\TemplateItemRequest;
use App\Http\Requests\Hr\UpdateTemplateDetailsRequest;
use App\Models\Ipcr;
use App\Models\IpcrTemplate;
use App\Models\RatingPeriod;
use App\Models\TemplateChangeLog;
use App\Models\TemplateItem;
use App\Services\TemplateManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function __construct(private TemplateManager $manager) {}

    public function index(TemplateFilterRequest $request): View|RedirectResponse
    {
        $filters = $request->validated();

        $period = isset($filters['period']) ? RatingPeriod::find($filters['period']) : RatingPeriod::current();
        if (! $period) {
            return redirect()->route('hr.periods.index')->withErrors(['template' => 'Create a rating period first. Templates belong to a rating period.']);
        }
        $type = $filters['type'] ?? 'teaching';

        $versions = IpcrTemplate::for($period->id, $type)->with('copiedFrom.period')->withCount('items')->orderByDesc('version')->get();
        $template = isset($filters['version'])
            ? $versions->firstWhere('version', (int) $filters['version'])
            : $versions->first();
        $template?->load(['items', 'copiedFrom.period']);

        return view('hr.templates', [
            'period' => $period,
            'type' => $type,
            'periods' => RatingPeriod::orderByDesc('starts_on')->get(),
            'versions' => $versions,
            'template' => $template,
            'active' => IpcrTemplate::activeFor($period->id, $type),
            'hasDraft' => $versions->contains(fn ($t) => $t->isDraft()),
            'usage' => Ipcr::whereIn('ipcr_template_id', $versions->pluck('id'))->selectRaw('ipcr_template_id, count(*) as total')->groupBy('ipcr_template_id')->pluck('total', 'ipcr_template_id'),
            'copySources' => IpcrTemplate::with('period')->where('status', 'published')->orderByDesc('rating_period_id')->orderBy('employee_type')->orderByDesc('version')->get(),
            'logs' => TemplateChangeLog::whereIn('ipcr_template_id', $versions->pluck('id'))->with(['user', 'template'])->latest('id')->paginate(15, pageName: 'log_page')->withQueryString(),
            'editing' => $filters['edit'] ?? null,
            'programOutcomes' => $template ? $template->items->where('section', 'strategic')->pluck('program_outcome')->filter()->unique()->values() : collect(),
        ]);
    }

    /** Start a new draft version, optionally copied from an existing version. */
    public function store(StoreTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $period = RatingPeriod::findOrFail($data['period']);
        $source = isset($data['copy_from']) ? IpcrTemplate::with(['items', 'period'])->find($data['copy_from']) : null;
        $template = $this->manager->newDraft($period, $data['type'], $source, $request->user());

        return $this->backTo($template, 'Version '.$template->version.' was created as a draft. Edit it, then publish it.');
    }

    public function update(UpdateTemplateDetailsRequest $request, IpcrTemplate $template): RedirectResponse
    {
        $this->manager->updateDetails($template->load('period'), $request->details(), $request->user());

        return $this->backTo($template, 'Form details saved.');
    }

    public function publish(Request $request, IpcrTemplate $template): RedirectResponse
    {
        $this->manager->publish($template->load('period'), $request->user());

        return $this->backTo($template, 'Version '.$template->version.' is published. New IPCRs will use it; IPCRs already started keep their own version.');
    }

    public function storeItem(TemplateItemRequest $request, IpcrTemplate $template): RedirectResponse
    {
        $item = $this->manager->addItem($template->load('period'), $request->validated(), $request->user());

        return $this->backTo($template, 'Indicator added.', $item->section);
    }

    public function updateItem(TemplateItemRequest $request, IpcrTemplate $template, TemplateItem $item): RedirectResponse
    {
        $this->manager->updateItem($item->load('template.period'), $request->validated(), $request->user());

        return $this->backTo($template, 'Indicator saved.', $item->section);
    }

    public function destroyItem(Request $request, IpcrTemplate $template, TemplateItem $item): RedirectResponse
    {
        $section = $item->section;
        $this->manager->removeItem($item->load('template.period'), $request->user());

        return $this->backTo($template, 'Indicator removed.', $section);
    }

    private function backTo(IpcrTemplate $template, string $message, ?string $anchor = null): RedirectResponse
    {
        $url = route('hr.templates', [
            'period' => $template->rating_period_id,
            'type' => $template->employee_type,
            'version' => $template->version,
        ]);

        return redirect($url.($anchor ? '#'.$anchor : ''))->with('status', $message);
    }
}
