<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Which employees an analytics view may count. Built from the signed-in user's
 * own unit; a requested college or department outside it is refused with 403.
 */
final class AnalyticsScope
{
    private function __construct(
        public readonly ?int $collegeId,
        public readonly ?int $departmentId,
        public readonly string $label,
    ) {}

    public static function forUser(User $user, ?int $collegeId = null, ?int $departmentId = null): self
    {
        return match ($user->role) {
            Role::DepartmentHead => self::departmentHead($user, $collegeId, $departmentId),
            Role::Dean => self::dean($user, $collegeId, $departmentId),
            Role::Hr => self::hr($collegeId, $departmentId),
            default => abort(403, 'Analytics are only available to reviewers.'),
        };
    }

    private static function departmentHead(User $user, ?int $collegeId, ?int $departmentId): self
    {
        abort_if($user->department_id === null, 403, 'Your account has no department. Ask the Administrator to assign one.');
        abort_if($collegeId !== null && $collegeId !== $user->college_id, 403, 'You can only view analytics for your own department.');
        abort_if($departmentId !== null && $departmentId !== $user->department_id, 403, 'You can only view analytics for your own department.');

        return new self($user->college_id, $user->department_id, $user->unitLabel());
    }

    private static function dean(User $user, ?int $collegeId, ?int $departmentId): self
    {
        abort_if($user->college_id === null, 403, 'Your account has no college or office. Ask the Administrator to assign one.');
        abort_if($collegeId !== null && $collegeId !== $user->college_id, 403, 'You can only view analytics for your own college or office.');

        $college = College::findOrFail($user->college_id);
        if ($departmentId !== null) {
            $department = Department::where('college_id', $college->id)->find($departmentId);
            abort_unless($department, 403, 'That department is not part of your college or office.');

            return new self($college->id, $department->id, $college->name.' · '.$department->name);
        }

        return new self($college->id, null, $college->name);
    }

    private static function hr(?int $collegeId, ?int $departmentId): self
    {
        $college = $collegeId ? College::findOrFail($collegeId) : null;
        $department = $departmentId ? Department::findOrFail($departmentId) : null;

        if ($department && $college && $department->college_id !== $college->id) {
            abort(422, 'That department does not belong to the selected college or office.');
        }

        $label = match (true) {
            (bool) $department => ($college ?? $department->college)->name.' · '.$department->name,
            (bool) $college => $college->name,
            default => 'All colleges and offices',
        };

        return new self($college?->id ?? $department?->college_id, $department?->id, $label);
    }

    /** Limit a query that has the users table joined (or is on users) to this scope. */
    public function apply(QueryBuilder|EloquentBuilder $query, string $users = 'users'): QueryBuilder|EloquentBuilder
    {
        return $query
            ->when($this->collegeId, fn ($q) => $q->where("{$users}.college_id", $this->collegeId))
            ->when($this->departmentId, fn ($q) => $q->where("{$users}.department_id", $this->departmentId));
    }

    public function isDepartment(): bool
    {
        return $this->departmentId !== null;
    }
}
