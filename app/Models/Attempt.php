<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Attempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'student_id',
        'stage_id',
        'story_id',
        'total',
        'correct_count',
        'pct',
        'stars',
        'exp_earned',
        'answers',
        'duration_sec',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'pct' => 'decimal:2',
            'answers' => 'array',
        ];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function story()
    {
        return $this->belongsTo(Story::class);
    }
}
