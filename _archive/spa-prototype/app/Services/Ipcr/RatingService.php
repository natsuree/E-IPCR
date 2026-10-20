<?php

namespace App\Services\Ipcr;

/**
 * Port of dist/js/model.js rating helpers (rowAverage + rating).
 *
 *  - Row mean  = (Q + E + T) / 3 when all three are valid 1–5 numbers.
 *  - Category  = arithmetic mean of fully rated, applicable rows.
 *  - Overall   = Σ(category mean × weight / 100); null ("Not yet rated") when a
 *                category with positive weight has no rated rows.
 *  - Label     = first scale threshold ≤ total, else "Poor".
 */
class RatingService
{
    public const CATEGORIES = ['Strategic', 'Core', 'Support'];

    /** Numeric coercion mirroring JS Number.isFinite(+v); '' and non-numeric strings are null. */
    public static function numeric(mixed $value): ?float
    {
        if ($value === '' || $value === null || is_bool($value)) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || ! is_numeric($trimmed)) {
                return null;
            }
            $number = (float) $trimmed;

            return is_finite($number) ? $number : null;
        }

        return null;
    }

    /** @param  array<int, mixed>  $row  prototype row shape (array or IpcrRow model) */
    public function rowAverage(array|object $row): ?float
    {
        $values = [
            is_array($row) ? ($row['q'] ?? null) : $row['q'],
            is_array($row) ? ($row['e'] ?? null) : $row['e'],
            is_array($row) ? ($row['t'] ?? null) : $row['t'],
        ];

        $sum = 0.0;
        foreach ($values as $value) {
            $number = self::numeric($value);
            if ($number === null || $number < 1 || $number > 5) {
                return null;
            }
            $sum += $number;
        }

        return $sum / 3;
    }

    /**
     * @param  iterable<int, array|object>  $rows
     * @param  array{weights: array<int, float>, scale: array<int, array{min: float, label: string}>}  $settings
     * @return array{categories: array<int, array{name: string, weight: float, average: float|null}>, total: float|null, label: string}
     */
    public function rating(iterable $rows, array $settings): array
    {
        $categories = [];

        foreach (self::CATEGORIES as $index => $category) {
            $values = [];
            foreach ($rows as $row) {
                $rowCategory = is_array($row) ? ($row['category'] ?? null) : $row['category'];
                $applicable = is_array($row) ? ($row['applicable'] ?? null) : $row['applicable'];
                if ($rowCategory === $category && $applicable) {
                    $average = $this->rowAverage($row);
                    if ($average !== null) {
                        $values[] = $average;
                    }
                }
            }

            $categories[] = [
                'name' => $category,
                'weight' => (float) ($settings['weights'][$index] ?? 0),
                'average' => $values === [] ? null : array_sum($values) / count($values),
            ];
        }

        $complete = true;
        foreach ($categories as $category) {
            if ($category['weight'] !== 0 && $category['average'] === null) {
                $complete = false;
                break;
            }
        }

        $total = null;
        if ($complete) {
            $total = 0.0;
            foreach ($categories as $category) {
                $total += ($category['average'] ?? 0) * $category['weight'] / 100;
            }
        }

        $label = 'Not yet rated';
        if ($total !== null) {
            $label = 'Poor';
            foreach ($settings['scale'] as $threshold) {
                if ($total >= (float) $threshold['min']) {
                    $label = $threshold['label'];
                    break;
                }
            }
        }

        return ['categories' => $categories, 'total' => $total, 'label' => $label];
    }
}
