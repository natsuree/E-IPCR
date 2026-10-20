<?php

namespace App\Services\Ipcr;

use App\Models\IpcrRecord;
use App\Models\IpcrRow;
use Illuminate\Support\Str;

/** Creates/normalizes IPCR rows from prototype-shaped arrays. */
class IpcrRowFactory
{
    public static function create(IpcrRecord $record, array $row, ?string $id = null): IpcrRow
    {
        return IpcrRow::create([
            'id' => $id ?? (string) Str::uuid(),
            'record_id' => $record->id,
        ] + self::attributes($row));
    }

    /** Prototype row array → database columns; raw Q/E/T values are preserved exactly. */
    public static function attributes(array $row): array
    {
        $string = fn ($value): string => is_scalar($value) ? (string) $value : '';
        $rating = fn ($value) => (is_scalar($value) && ! is_bool($value)) ? $value : '';

        $category = $row['category'] ?? 'Strategic';

        return [
            'category' => in_array($category, RatingService::CATEGORIES, true) ? $category : 'Strategic',
            'applicable' => (bool) ($row['applicable'] ?? true),
            'program' => $string($row['program'] ?? ''),
            'outcome' => $string($row['outcome'] ?? ''),
            'indicator_type' => $string($row['indicatorType'] ?? 'Function'),
            'indicator' => $string($row['indicator'] ?? ''),
            'target' => $string($row['target'] ?? ''),
            'actual' => $string($row['actual'] ?? ''),
            'q' => $rating($row['q'] ?? ''),
            'e' => $rating($row['e'] ?? ''),
            't' => $rating($row['t'] ?? ''),
            'remarks' => $string($row['remarks'] ?? ''),
        ];
    }
}
