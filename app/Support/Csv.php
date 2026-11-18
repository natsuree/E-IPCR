<?php

namespace App\Support;

/**
 * CSV output that spreadsheet programs will not run as formulas (CSV/formula injection).
 * A text cell starting with = + - @ or a tab/carriage return gets a leading apostrophe,
 * which Excel and LibreOffice show as plain text. Numbers are left as numbers.
 */
final class Csv
{
    public static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $value = (string) $value;
        if ($value !== '' && is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /** @param  resource  $handle */
    public static function row($handle, array $cells): void
    {
        fputcsv($handle, array_map(self::cell(...), $cells));
    }
}
