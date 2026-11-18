<?php

namespace App\Services;

use App\Models\Ipcr;
use App\Models\IpcrTemplate;
use App\Models\TemplateItem;
use Illuminate\Support\Facades\DB;

/**
 * When HR publishes a template version, unsubmitted drafts (status "draft") of matching
 * employees move to the version that now applies to them. Accomplishments and MOV links
 * on carried-over targets are kept, employee-added targets are never touched, and template
 * rows that no longer apply are kept as retired (not deleted). Submitted, returned and
 * completed IPCRs keep the version they have.
 */
class DraftTemplateUpdate
{
    private const FIELDS = ['section', 'program_outcome', 'indicator', 'target', 'sort_order'];

    public function forPeriod(int $periodId, string $type): void
    {
        DB::transaction(function () use ($periodId, $type) {
            Ipcr::where('rating_period_id', $periodId)->where('status', 'draft')
                ->whereHas('user', fn ($q) => $q->where('employee_type', $type))
                ->with(['user', 'items', 'retiredTargets', 'template.items'])->lockForUpdate()
                ->chunkById(100, function ($drafts) use ($periodId, $type) {
                    foreach ($drafts as $draft) {
                        $template = IpcrTemplate::activeFor($periodId, $type, $draft->user);
                        if ($template && $template->id !== $draft->ipcr_template_id) {
                            $this->update($draft, $template);
                        }
                    }
                });
        });
    }

    private function update(Ipcr $draft, IpcrTemplate $template): void
    {
        // Identify the original HR rows by their immutable template snapshot.
        $remaining = $draft->items->whereNull('employee_target_key')->keyBy('id');
        $bySource = [];
        foreach ($draft->template->items as $source) {
            $row = $remaining->first(fn ($item) => $item->only(self::FIELDS) === $source->only(self::FIELDS));
            if ($row) {
                $bySource[$source->id] = $row;
                $remaining->forget($row->id);
            }
        }
        $unused = $draft->items->whereNull('employee_target_key')->keyBy('id');
        foreach ($template->items as $target) {
            $source = $target;
            $match = null;
            while ($source->copied_item_id) {
                $match = $bySource[$source->copied_item_id] ?? null;
                if ($match) {
                    break;
                }
                $source = TemplateItem::find($source->copied_item_id);
                if (! $source) {
                    break;
                }
            }
            // Older template versions lack copy IDs. Reuse identical rows only.
            $row = $match ? $unused->get($match->id) : $unused->first(fn ($item) => $item->only(self::FIELDS) === $target->only(self::FIELDS));
            if ($row) {
                $row->update($target->only(self::FIELDS));
                $unused->forget($row->id);
            } elseif ($retired = $this->retiredMatch($draft, $target)) {
                // HR brought back an indicator retired by an earlier version: reuse the row, keeping its entries.
                $retired->forceFill($target->only(self::FIELDS) + ['retired' => false])->save();
            } else {
                $draft->items()->create($target->only(self::FIELDS));
            }
        }
        foreach ($unused as $row) {
            $row->forceFill(['retired' => true])->save();
        }
        $draft->update(['ipcr_template_id' => $template->id]);
    }

    /** A template row of this draft retired earlier, with the same section and indicator. */
    private function retiredMatch(Ipcr $draft, TemplateItem $target): ?\App\Models\IpcrItem
    {
        $match = $draft->retiredTargets->first(fn ($item) => $item->employee_target_key === null
            && $item->section === $target->section && $item->indicator === $target->indicator);
        if ($match) {
            $draft->retiredTargets->forget($draft->retiredTargets->search($match));
        }

        return $match;
    }
}
