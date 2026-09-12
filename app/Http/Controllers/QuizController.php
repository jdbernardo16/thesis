<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Services\GradingService;
use App\Services\UnlockService;
use Inertia\Inertia;

class QuizController extends Controller
{
    public function show(Story $story)
    {
        $user = request()->user();
        $story->loadMissing(['stage.level', 'questions' => fn ($q) => $q->where('is_active', true)->orderBy('order')]);
        $stage = $story->stage;

        abort_if(! $stage, 404);
        abort_if(! $stage->is_published, 403, 'This quiz is not published yet.');
        abort_if($stage->level && ! $stage->level->is_published, 403, 'This quiz is not published yet.');

        if ($user && $user->role === 'student') {
            abort_unless(
                UnlockService::stageUnlocked($user->id, $stage),
                403,
                'Clear previous stage first.'
            );
        }

        $questions = $story->questions->map(fn ($q) => self::clientSafe($q))->values();

        return Inertia::render('Student/Quiz', [
            'story' => [
                'id' => $story->id,
                'title' => $story->title,
                'type' => $story->type,
            ],
            'stage' => [
                'id' => $stage->id,
                'title' => $stage->title,
                'order' => $stage->order,
            ],
            'questions' => $questions,
            'bestStars' => $user ? UnlockService::bestStars($user->id, $stage->id) : 0,
        ]);
    }

    public static function clientSafe($question): array
    {
        $payload = $question->payload ?? [];
        $type = GradingService::normalizeType((string) $question->type);

        $safe = [
            'id' => $question->id,
            'order' => $question->order,
            'type' => $type,
            'stem' => $question->stem,
        ];

        if ($type === 'mc_single') {
            $safe['options'] = array_values((array) ($payload['options'] ?? $payload['choices'] ?? []));
        }

        if ($type === 'ordering') {
            $items = $payload['items'] ?? null;
            $safe['items'] = $items !== null
                ? array_values((array) $items)
                : array_values(array_reverse((array) ($payload['correct_order'] ?? [])));
        }

        return $safe;
    }
}
