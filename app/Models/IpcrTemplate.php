<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpcrTemplate extends Model
{
    use SoftDeletes;

    public const DEFAULT_SCALE = [
        5 => 'Outstanding',
        4 => 'Very Satisfactory',
        3 => 'Satisfactory',
        2 => 'Unsatisfactory',
        1 => 'Poor',
    ];

    public const DEFAULT_SIGNATORIES = [
        'ratee' => 'Ratee',
        'reviewed_by' => 'Reviewed by (Department Head)',
        'noted_by' => 'Noted by (Dean/Director)',
        'final_rating_by' => 'Final rating by (HR Personnel)',
    ];

    public const SIGNATORY_FIELDS = [
        'ratee' => 'Ratee',
        'reviewed_by' => 'Reviewed by',
        'noted_by' => 'Noted by',
        'final_rating_by' => 'Final rating by',
    ];

    protected $fillable = [
        'rating_period_id',
        'employee_type',
        'version',
        'status',
        'published_at',
        'copied_from_id',
        'commitment_period',
        'commitment_statement',
        'rating_scale',
        'signatories',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'rating_scale' => 'array',
            'signatories' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RatingPeriod::class, 'rating_period_id');
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(TemplateChangeLog::class)->latest('id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function scopeFor(Builder $query, int $periodId, string $employeeType): Builder
    {
        return $query->where('rating_period_id', $periodId)->where('employee_type', $employeeType);
    }

    /** The version new IPCRs use: the highest published version for the period and type. */
    public static function activeFor(int $periodId, string $employeeType): ?self
    {
        return static::for($periodId, $employeeType)
            ->where('status', 'published')
            ->orderByDesc('version')
            ->first();
    }

    public function isActive(): bool
    {
        return $this->isPublished()
            && static::activeFor($this->rating_period_id, $this->employee_type)?->is($this);
    }

    public function employeeTypeLabel(): string
    {
        return $this->employee_type === 'teaching' ? 'Teaching' : 'Non-teaching';
    }

    /** "Version 2 · Published" */
    public function versionLabel(): string
    {
        return 'Version '.$this->version.' · '.($this->isPublished() ? 'Published' : 'Draft');
    }

    /** Rating scale ordered 5 → 1 with integer keys. */
    public function scale(): array
    {
        $scale = [];
        foreach (self::DEFAULT_SCALE as $value => $label) {
            $scale[$value] = $this->rating_scale[$value] ?? $this->rating_scale[(string) $value] ?? $label;
        }

        return $scale;
    }

    /** The statement written to follow "I, <name>, <position> of <unit>," (drops a leading "I "). */
    public function commitmentClause(): string
    {
        return preg_replace('/^I\s+/', '', trim($this->commitment_statement));
    }

    public function signatory(string $key): string
    {
        return $this->signatories[$key] ?? self::DEFAULT_SIGNATORIES[$key] ?? '';
    }
}
