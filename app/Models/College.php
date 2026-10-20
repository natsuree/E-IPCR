<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class College extends Model
{
    protected $fillable = ['name', 'code', 'kind', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class)->orderBy('name');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isOffice(): bool
    {
        return $this->kind === 'office';
    }
}
