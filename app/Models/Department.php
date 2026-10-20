<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['college_id', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
