<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatingPeriod extends Model
{
    public const STATUSES = [
        'draft' => 'Not yet open',
        'open' => 'Open',
        'closed' => 'Closed',
    ];

    protected $fillable = ['name', 'starts_on', 'ends_on', 'submission_deadline', 'status', 'opened_at', 'closed_at'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'submission_deadline' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** The period employees can create IPCRs for (at most one is open). */
    public static function open(): ?self
    {
        return static::where('status', 'open')->orderByDesc('starts_on')->first();
    }

    /** The period dashboards show: the open one, else the latest that has been opened, else the latest. */
    public static function current(): ?self
    {
        return static::open()
            ?? static::where('status', 'closed')->orderByDesc('starts_on')->first()
            ?? static::orderByDesc('starts_on')->first();
    }

    public function ipcrs(): HasMany
    {
        return $this->hasMany(Ipcr::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(IpcrTemplate::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** True once the submission deadline day has ended. */
    public function deadlinePassed(): bool
    {
        return $this->submission_deadline !== null && now()->startOfDay()->gt($this->submission_deadline);
    }
}
