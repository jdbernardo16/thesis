<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'story_id',
        'order',
        'type',
        'stem',
        'payload',
        'points',
        'explanation',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function story()
    {
        return $this->belongsTo(Story::class);
    }
}
