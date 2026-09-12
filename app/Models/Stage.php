<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    protected $fillable = [
        'level_id',
        'title',
        'order',
        'required_stars_to_unlock',
        'is_pretest',
        'is_posttest',
        'difficulty_tag',
        'readability_note',
        'estimated_minutes',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_pretest' => 'boolean',
            'is_posttest' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function story()
    {
        return $this->hasOne(Story::class);
    }
}
