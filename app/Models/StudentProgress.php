<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentProgress extends Model
{
    protected $table = 'student_progress';

    protected $primaryKey = 'student_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'student_id',
        'total_stars',
        'total_exp',
        'stages_cleared',
        'stages_perfect',
        'current_level_id',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function currentLevel()
    {
        return $this->belongsTo(Level::class, 'current_level_id');
    }
}
