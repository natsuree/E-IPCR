<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'role',
        'position',
        'unit',
        'department',
        'type',
        'active',
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
            'active' => 'boolean',
        ];
    }

    public function periods(): BelongsToMany
    {
        return $this->belongsToMany(EvaluationPeriod::class, 'evaluation_period_user', 'user_id', 'period_id');
    }

    public function ownedRecords(): HasMany
    {
        return $this->hasMany(IpcrRecord::class, 'owner_id');
    }

    /** Employee roles are the ones that create and edit IPCRs (prototype rule). */
    public function isEmployee(): bool
    {
        return str_ends_with($this->role, 'Employee');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'Administrator';
    }

    public function periodIds(): array
    {
        return $this->periods()->pluck('id')->all();
    }

    /** Prototype-shaped payload. Sensitive fields are only exposed to administrators and self. */
    public function toApi(bool $full = false): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'position' => $this->position,
            'unit' => $this->unit,
            'department' => $this->department,
            'type' => $this->type,
            'active' => $this->active,
        ];

        if ($full) {
            $data['email'] = $this->email;
            $data['periodIds'] = $this->periodIds();
        }

        return $data;
    }
}
