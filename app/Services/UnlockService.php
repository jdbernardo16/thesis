<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Level;
use App\Models\Stage;

class UnlockService
{
    public static function bestStars(int $studentId, int $stageId): int
    {
        return (int) Attempt::where('student_id', $studentId)
            ->where('stage_id', $stageId)
            ->max('stars');
    }

    public static function stageUnlocked(int $studentId, Stage $stage): bool
    {
        $stage->loadMissing('level');

        if ($stage->order === 1 && $stage->level->order === 1) {
            return true;
        }

        $prev = Stage::where('level_id', $stage->level_id)
            ->where('order', '<', $stage->order)
            ->orderByDesc('order')
            ->first();

        if ($prev) {
            return self::bestStars($studentId, $prev->id) >= $stage->required_stars_to_unlock;
        }

        $prevLevel = Level::where('order', '<', $stage->level->order)
            ->orderByDesc('order')
            ->first();

        if (! $prevLevel) {
            return true;
        }

        $sum = Attempt::where('student_id', $studentId)
            ->whereIn('stage_id', $prevLevel->stages()->pluck('stages.id'))
            ->selectRaw('stage_id, MAX(stars) m')
            ->groupBy('stage_id')
            ->get()
            ->sum('m');

        return $sum >= $stage->level->required_total_stars_to_unlock;
    }
}
