<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\College;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Reads a CSV of accounts and checks every row before anything is saved.
 * Columns: name, email, employee_id, role, employee_type, college_office, department.
 */
class UserImporter
{
    public const MAX_ROWS = 1000;

    public const COLUMNS = ['name', 'email', 'employee_id', 'role', 'employee_type', 'college_office', 'department'];

    private const HEADER_ALIASES = [
        'full_name' => 'name',
        'employee_no' => 'employee_id',
        'employee_number' => 'employee_id',
        'type' => 'employee_type',
        'college' => 'college_office',
        'office' => 'college_office',
        'college/office' => 'college_office',
        'college_/_office' => 'college_office',
        'unit' => 'college_office',
    ];

    private const ROLE_ALIASES = [
        'teaching employee' => 'teaching', 'teaching' => 'teaching',
        'non-teaching employee' => 'nonteaching', 'non-teaching' => 'nonteaching', 'nonteaching' => 'nonteaching', 'non teaching' => 'nonteaching',
        'department head' => 'depthead', 'depthead' => 'depthead', 'head' => 'depthead',
        'dean/director' => 'dean', 'dean' => 'dean', 'director' => 'dean',
        'hr personnel' => 'hr', 'hr' => 'hr',
        'administrator' => 'admin', 'admin' => 'admin',
    ];

    /**
     * @return array{rows: list<array{line: int, values: array, data: ?array, errors: list<string>}>, valid: int, invalid: int, fileErrors: list<string>}
     */
    public function preview(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = $handle ? fgetcsv($handle) : false;

        if (! $header) {
            return $this->fileError('The file is empty. The first row must be the column names: '.implode(', ', self::COLUMNS).'.');
        }

        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $header);
        $missing = array_diff(['name', 'email', 'role', 'college_office'], $header);
        if ($missing) {
            fclose($handle);

            return $this->fileError('The file is missing these columns: '.implode(', ', $missing).'. The first row must be: '.implode(', ', self::COLUMNS).'.');
        }

        $colleges = College::with('departments')->where('active', true)->get();
        $seenEmails = [];
        $seenIds = [];
        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                return $this->fileError('The file has more than '.self::MAX_ROWS.' rows. Split it into smaller files.');
            }

            $values = [];
            foreach (self::COLUMNS as $column) {
                $index = array_search($column, $header, true);
                $values[$column] = $index === false ? '' : trim((string) ($cells[$index] ?? ''));
            }

            [$data, $errors] = $this->check($values, $colleges, $seenEmails, $seenIds, $line);
            $rows[] = ['line' => $line, 'values' => $values, 'data' => $errors ? null : $data, 'errors' => $errors];
        }
        fclose($handle);

        if (! $rows) {
            return $this->fileError('The file has column names but no accounts.');
        }

        $invalid = count(array_filter($rows, fn ($r) => $r['errors']));

        return ['rows' => $rows, 'valid' => count($rows) - $invalid, 'invalid' => $invalid, 'fileErrors' => []];
    }

    private function check(array $v, $colleges, array &$seenEmails, array &$seenIds, int $line): array
    {
        $errors = [];

        if ($v['name'] === '') {
            $errors[] = 'Name is missing.';
        }

        $email = Str::lower($v['email']);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is missing or not a valid address.';
        } elseif (isset($seenEmails[$email])) {
            $errors[] = "Email is used twice in this file (also on row {$seenEmails[$email]}).";
        } elseif (User::withTrashed()->where('email', $email)->exists()) {
            $errors[] = 'An account with this email already exists.';
        }

        if ($v['employee_id'] !== '') {
            if (isset($seenIds[$v['employee_id']])) {
                $errors[] = 'Employee ID is used twice in this file.';
            } elseif (User::withTrashed()->where('employee_id', $v['employee_id'])->exists()) {
                $errors[] = 'Another account already has this employee ID.';
            }
        }

        $roleKey = self::ROLE_ALIASES[Str::lower($v['role'])] ?? null;
        $role = $roleKey ? Role::from($roleKey) : null;
        if (! $role) {
            $errors[] = 'Role must be one of: '.implode(', ', Role::options()).'.';
        }

        $type = match (Str::lower(str_replace(' ', '', $v['employee_type']))) {
            'teaching' => 'teaching',
            'non-teaching', 'nonteaching' => 'nonteaching',
            '' => null,
            default => false,
        };
        if ($type === false) {
            $errors[] = 'Employee type must be Teaching or Non-teaching.';
        } elseif ($role?->isEmployee() && $type !== null && $type !== $role->employeeType()) {
            $errors[] = "Employee type \"{$v['employee_type']}\" does not match the role {$role->label()}.";
        }

        $college = null;
        $department = null;
        if ($v['college_office'] !== '') {
            $needle = Str::lower($v['college_office']);
            $college = $colleges->first(fn ($c) => Str::lower($c->name) === $needle || ($c->code && Str::lower($c->code) === $needle));
            if (! $college) {
                $errors[] = "College/office \"{$v['college_office']}\" was not found. Use the exact name or code from Colleges & Offices.";
            }
        } elseif ($role !== Role::Admin) {
            $errors[] = 'College/office is missing.';
        }

        if ($v['department'] !== '' && $college) {
            $department = $college->departments->first(fn ($d) => $d->active && Str::lower($d->name) === Str::lower($v['department']));
            if (! $department) {
                $errors[] = "Department \"{$v['department']}\" is not an active department of {$college->name}.";
            }
        } elseif ($role === Role::DepartmentHead) {
            $errors[] = 'A Department Head needs a department.';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $seenEmails[$email] ??= $line;
        }
        if ($v['employee_id'] !== '') {
            $seenIds[$v['employee_id']] = true;
        }

        return [[
            'name' => $v['name'],
            'email' => $email,
            'employee_id' => $v['employee_id'] ?: null,
            'role' => $role?->value,
            'employee_type' => $role?->employeeType(),
            'college_id' => $college?->id,
            'department_id' => $department?->id,
            'active' => true,
        ], $errors];
    }

    private function normalizeHeader(string $header): string
    {
        $key = Str::of($header)->replace("\u{FEFF}", '')->trim()->lower()->replace(['-', ' '], '_')->toString();

        return self::HEADER_ALIASES[$key] ?? $key;
    }

    private function fileError(string $message): array
    {
        return ['rows' => [], 'valid' => 0, 'invalid' => 0, 'fileErrors' => [$message]];
    }
}
