<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['weights', 'scale'];

    protected $casts = [
        'weights' => 'array',
        'scale' => 'array',
    ];

    /** The single active settings row (matching the prototype's global db.settings). */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::create(static::defaults());
    }

    public static function defaults(): array
    {
        return [
            'weights' => [45, 45, 10],
            'scale' => [
                ['min' => 4.5, 'label' => 'Outstanding'],
                ['min' => 3.5, 'label' => 'Very Satisfactory'],
                ['min' => 2.5, 'label' => 'Satisfactory'],
                ['min' => 1.5, 'label' => 'Unsatisfactory'],
                ['min' => 1, 'label' => 'Poor'],
            ],
        ];
    }
}
