<?php

namespace App\Http\Controllers;

use App\Models\Level;
use App\Services\UnlockService;
use Inertia\Inertia;

class MapController extends Controller
{
    public function index()
    {
        $user = request()->user();
        $studentOnly = $user && $user->role === 'student';

        $levels = Level::orderBy('order')
            ->when($studentOnly, fn ($q) => $q->where('is_published', true))
            ->with(['stages' => function ($q) use ($studentOnly) {
                $q->orderBy('order');
                if ($studentOnly) {
                    $q->where('is_published', true);
                }
                $q->with('story:id,stage_id');
            }])
            ->get();

        $payload = $levels->map(function ($level) use ($user) {
            $stages = $level->stages->map(function ($stage) use ($user) {
                $bestStars = UnlockService::bestStars($user->id, $stage->id);
                $unlocked = UnlockService::stageUnlocked($user->id, $stage);

                $state = 'locked';
                if ($unlocked) {
                    $state = $bestStars > 0 ? 'cleared' : 'current';
                }

                return [
                    'id' => $stage->id,
                    'title' => $stage->title,
                    'order' => $stage->order,
                    'required_stars_to_unlock' => $stage->required_stars_to_unlock,
                    'is_published' => $stage->is_published,
                    'is_pretest' => $stage->is_pretest,
                    'is_posttest' => $stage->is_posttest,
                    'story_id' => $stage->story?->id,
                    'bestStars' => $bestStars,
                    'unlocked' => $unlocked,
                    'state' => $state,
                ];
            });

            return [
                'id' => $level->id,
                'title' => $level->title,
                'description' => $level->description,
                'order' => $level->order,
                'required_total_stars_to_unlock' => $level->required_total_stars_to_unlock,
                'badge_name' => $level->badge_name,
                'is_published' => $level->is_published,
                'stages' => $stages,
            ];
        });

        return Inertia::render('Student/Map', [
            'levels' => $payload,
        ]);
    }
}
