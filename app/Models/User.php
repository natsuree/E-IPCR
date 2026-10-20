<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'employee_type',
        'position',
        'college_id',
        'department_id',
        'active',
        'employee_id',
    ];

    protected $attributes = [
        'active' => true,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
        ];
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function ipcrs(): HasMany
    {
        return $this->hasMany(Ipcr::class);
    }

    public function isEmployee(): bool
    {
        return $this->role->isEmployee();
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Route-name prefix for the user's own pages (employee, depthead, dean, hr, admin). */
    public function routePrefix(): string
    {
        return $this->role->prefix();
    }

    public function dashboardRoute(): string
    {
        return route($this->routePrefix().'.dashboard');
    }

    /** "College of Engineering · Computer Engineering", "Records Office" or "System administration". */
    public function unitLabel(): string
    {
        if (! $this->college) {
            return 'System administration';
        }

        return $this->department
            ? $this->college->name.' · '.$this->department->name
            : $this->college->name;
    }

    public function employeeTypeLabel(): string
    {
        return match ($this->employee_type) {
            'teaching' => 'Teaching',
            'nonteaching' => 'Non-teaching',
            default => '—',
        };
    }

    /** Accounts that prepare IPCRs. */
    public function scopeEmployees(Builder $query): Builder
    {
        return $query->whereIn('role', [Role::Teaching->value, Role::NonTeaching->value]);
    }

    /** Limit a user query to the people a reviewer is responsible for. */
    public function scopeWithinScopeOf(Builder $query, User $viewer): Builder
    {
        return match ($viewer->role) {
            Role::DepartmentHead => $query->where('department_id', $viewer->department_id),
            Role::Dean => $query->where('college_id', $viewer->college_id),
            Role::Hr => $query,
            Role::Teaching, Role::NonTeaching => $query->whereKey($viewer->id),
            Role::Admin => $query->whereRaw('1 = 0'),
        };
    }

    /** Whether $employee falls inside this reviewer's unit scope. */
    public function covers(User $employee): bool
    {
        return match ($this->role) {
            Role::DepartmentHead => $this->department_id !== null && $employee->department_id === $this->department_id,
            Role::Dean => $this->college_id !== null && $employee->college_id === $this->college_id,
            Role::Hr => true,
            Role::Teaching, Role::NonTeaching => $employee->id === $this->id,
            Role::Admin => false,
        };
    }
}
