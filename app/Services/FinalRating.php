<?php

namespace App\Services;

use App\Models\Ipcr;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Final rating computation. The method, section weights and adjectival ranges
 * come from the "rating" setting, which HR edits on the Rating Settings page,
 * because MMSU HR has not confirmed them yet.
 */
class FinalRating
{
    public const SETTING = 'rating';

    public const METHODS = [
        'average' => 'Average of all rated indicators',
        'weighted' => 'Weighted average of the section averages',
    ];

    /** Lowest final rating for each adjectival rating; anything lower is "Poor". */
    public const DEFAULT_BANDS = [
        'Outstanding' => 4.50,
        'Very Satisfactory' => 3.50,
        'Satisfactory' => 2.50,
        'Unsatisfactory' => 1.50,
    ];

    public const LOWEST_BAND = 'Poor';

    public static function defaults(): array
    {
        return [
            'method' => 'average',
            'weights' => ['strategic' => 0, 'core' => 0, 'support' => 0],
            'bands' => self::DEFAULT_BANDS,
        ];
    }

    /** Current settings merged over the defaults. */
    public static function settings(): array
    {
        $saved = Setting::get(self::SETTING, []);
        $defaults = self::defaults();

        return [
            'method' => $saved['method'] ?? $defaults['method'],
            'weights' => array_merge($defaults['weights'], $saved['weights'] ?? []),
            'bands' => array_merge($defaults['bands'], $saved['bands'] ?? []),
        ];
    }

    /**
     * @param  Collection<int, \App\Models\IpcrItem>  $items
     * @return array{sections: array<string, float|null>, overall: float|null, adjectival: string|null, basis: array}
     */
    public static function compute(Collection $items, ?array $settings = null): array
    {
        $settings ??= self::settings();

        $sections = [];
        foreach (array_keys(Ipcr::SECTIONS) as $section) {
            $ratings = $items->where('section', $section)->pluck('rating')->filter(fn ($r) => $r !== null);
            $sections[$section] = $ratings->isEmpty() ? null : round($ratings->avg(), 2);
        }

        if ($settings['method'] === 'weighted') {
            $weighted = 0.0;
            $totalWeight = 0.0;
            foreach ($sections as $section => $average) {
                $weight = (float) ($settings['weights'][$section] ?? 0);
                if ($average !== null && $weight > 0) {
                    $weighted += $average * $weight;
                    $totalWeight += $weight;
                }
            }
            $overall = $totalWeight > 0 ? round($weighted / $totalWeight, 2) : null;
        } else {
            $all = $items->pluck('rating')->filter(fn ($r) => $r !== null);
            $overall = $all->isEmpty() ? null : round($all->avg(), 2);
        }

        return [
            'sections' => $sections,
            'overall' => $overall,
            'adjectival' => $overall === null ? null : self::adjectival($overall, $settings['bands']),
            'basis' => $settings,
        ];
    }

    public static function adjectival(float $rating, ?array $bands = null): string
    {
        $bands ??= self::settings()['bands'];
        arsort($bands);

        foreach ($bands as $label => $minimum) {
            if ($rating >= (float) $minimum) {
                return $label;
            }
        }

        return self::LOWEST_BAND;
    }

    /** "4.50–5.00 Outstanding · 3.50–4.49 Very Satisfactory · … · below 1.50 Poor" */
    public static function describeBands(array $bands): string
    {
        arsort($bands);
        $parts = [];
        $upper = 5.00;
        foreach ($bands as $label => $minimum) {
            $parts[] = number_format((float) $minimum, 2).'–'.number_format($upper, 2).' '.$label;
            $upper = (float) $minimum - 0.01;
        }
        $parts[] = 'below '.number_format((float) end($bands), 2).' '.self::LOWEST_BAND;

        return implode(' · ', $parts);
    }

    public static function describeMethod(array $settings): string
    {
        if ($settings['method'] !== 'weighted') {
            return self::METHODS['average'].', to two decimals.';
        }

        $weights = [];
        foreach (Ipcr::SECTIONS as $key => $label) {
            $weights[] = $label.' '.rtrim(rtrim(number_format((float) ($settings['weights'][$key] ?? 0), 2), '0'), '.').'%';
        }

        return self::METHODS['weighted'].' ('.implode(', ', $weights).'), to two decimals.';
    }
}
