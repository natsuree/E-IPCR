<?php

namespace App\Services;

use App\Models\IpcrTemplate;
use App\Models\Ipcr;
use App\Models\RatingPeriod;
use App\Models\Setting;
use App\Models\TemplateItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Template versions: a published version is never edited. Changes go into a
 * new draft version, which replaces the published one only when HR publishes it.
 */
class TemplateManager
{
    public function newDraft(RatingPeriod $period, string $type, ?IpcrTemplate $source, User $by): IpcrTemplate
    {
        $existingDraft = IpcrTemplate::for($period->id, $type)->where('status', 'draft')->first();
        if ($existingDraft) {
            throw ValidationException::withMessages(['template' => 'Version '.$existingDraft->version.' is already a draft for this period and type. Publish or edit that one.']);
        }

        return DB::transaction(function () use ($period, $type, $source, $by) {
            $version = (int) IpcrTemplate::for($period->id, $type)->max('version') + 1;

            $template = IpcrTemplate::create([
                'rating_period_id' => $period->id,
                'employee_type' => $type,
                'version' => $version,
                'status' => 'draft',
                'copied_from_id' => $source?->id,
                'commitment_period' => $source && $source->rating_period_id === $period->id ? $source->commitment_period : $period->name,
                'commitment_statement' => $source?->commitment_statement ?? self::defaultStatement(),
                'rating_scale' => $source?->rating_scale ?? Setting::get('rating_scale', IpcrTemplate::DEFAULT_SCALE),
                'signatories' => $source?->signatories ?? IpcrTemplate::DEFAULT_SIGNATORIES,
            ]);

            foreach ($source?->items ?? [] as $item) {
                $template->items()->create($item->only(['section', 'program_outcome', 'indicator', 'target', 'sort_order']));
            }

            $this->log($template, $by, $source
                ? "Created Version {$version} as a draft, copied from {$this->describe($source)}."
                : "Created Version {$version} as an empty draft.");

            return $template;
        });
    }

    public function publish(IpcrTemplate $template, User $by): void
    {
        $this->assertDraft($template);

        if ($template->items()->count() === 0) {
            throw ValidationException::withMessages(['template' => 'Add at least one indicator before publishing.']);
        }

        DB::transaction(function () use ($template, $by) {
            // Re-check inside the transaction so a double click cannot publish twice.
            $updated = IpcrTemplate::whereKey($template->id)->where('status', 'draft')->update(['status' => 'published', 'published_at' => now()]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['template' => 'This version was already published. Refresh the page to see it.']);
            }
            $template->refresh();
            $this->log($template, $by, "Published Version {$template->version}. New {$template->employeeTypeLabel()} IPCRs for {$template->period->name} now use this version.", 'template.published');
        });
    }

    public function updateDetails(IpcrTemplate $template, array $data, User $by): void
    {
        $this->assertDraft($template);

        $template->fill([
            'commitment_period' => $data['commitment_period'],
            'commitment_statement' => $data['commitment_statement'],
            'rating_scale' => $data['rating_scale'],
            'signatories' => $data['signatories'],
        ]);

        $changed = array_keys($template->getDirty());
        if (! $changed) {
            return;
        }
        $template->save();

        $labels = [
            'commitment_period' => 'commitment period',
            'commitment_statement' => 'commitment statement',
            'rating_scale' => 'rating scale labels',
            'signatories' => 'signatory labels',
        ];
        $this->log($template, $by, 'Updated form details: '.implode(', ', array_map(fn ($k) => $labels[$k], $changed)).'.');
    }

    public function addItem(IpcrTemplate $template, array $data, User $by): TemplateItem
    {
        $this->assertDraft($template);

        $item = $template->items()->create([
            'section' => $data['section'],
            'program_outcome' => $data['program_outcome'] ?? null,
            'indicator' => $data['indicator'],
            'target' => $data['target'],
            'sort_order' => (int) $template->items()->where('section', $data['section'])->max('sort_order') + 1,
        ]);

        $this->log($template, $by, 'Added to '.Ipcr::SECTIONS[$item->section].': "'.Str::limit($item->indicator, 80).'".');

        return $item;
    }

    public function updateItem(TemplateItem $item, array $data, User $by): void
    {
        $this->assertDraft($item->template);

        $before = Str::limit($item->indicator, 80);
        $item->fill([
            'program_outcome' => $data['program_outcome'] ?? null,
            'indicator' => $data['indicator'],
            'target' => $data['target'],
        ]);

        if (! $item->isDirty()) {
            return;
        }
        $fields = implode(', ', array_map(fn ($k) => str_replace('_', '/', $k), array_keys($item->getDirty())));
        $item->save();

        $this->log($item->template, $by, 'Edited '.Ipcr::SECTIONS[$item->section]." indicator \"{$before}\" ({$fields}).");
    }

    public function removeItem(TemplateItem $item, User $by): void
    {
        $this->assertDraft($item->template);

        $template = $item->template;
        $text = Str::limit($item->indicator, 80);
        $section = Ipcr::SECTIONS[$item->section];
        $item->delete();

        $this->log($template, $by, "Removed from {$section}: \"{$text}\".");
    }

    public function describe(IpcrTemplate $template): string
    {
        return "{$template->period->name} {$template->employeeTypeLabel()} Version {$template->version}";
    }

    public static function defaultStatement(): string
    {
        return 'I commit to deliver and agree to be rated on the attainment of the following targets in accordance with the indicated measures for the period';
    }

    private function assertDraft(IpcrTemplate $template): void
    {
        if (! $template->isDraft()) {
            throw ValidationException::withMessages(['template' => 'Published versions cannot be changed. Start a new draft version instead.']);
        }
    }

    private function log(IpcrTemplate $template, User $by, string $description, string $event = 'template.changed'): void
    {
        $template->changeLogs()->create(['user_id' => $by->id, 'description' => $description]);
        Audit::log($event, $template->employeeTypeLabel().' template, '.$template->period->name.': '.$description, $template, by: $by);
    }
}
