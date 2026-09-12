<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = [
        'title',
        'description',
        'order',
        'cover_path',
        'required_total_stars_to_unlock',
        'badge_name',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function stages()
    {
        return $this->hasMany(Stage::class)->orderBy('order');
    }
}
