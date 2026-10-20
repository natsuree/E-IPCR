<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationPeriod extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'start', 'end', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'evaluation_period_user', 'period_id', 'user_id');
    }
}
