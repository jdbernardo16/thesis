<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Services\UnlockService;
use Inertia\Inertia;

class StoryViewController extends Controller
{
    public function show(Story $story)
    {
        $user = request()->user();
        $story->loadMissing(['stage.level', 'questions' => fn ($q) => $q->where('is_active', true)->orderBy('order')]);
        $stage = $story->stage;

        abort_if(! $stage, 404);

        // Published guards: stage (and its level) must be published.
        abort_if(! $stage->is_published, 403, 'This story is not published yet.');
        abort_if($stage->level && ! $stage->level->is_published, 403, 'This story is not published yet.');

        // Unlock gate (admins/teachers bypass for preview).
        if ($user && $user->role === 'student') {
            abort_unless(
                UnlockService::stageUnlocked($user->id, $stage),
                403,
                'Clear previous stage first.'
            );
        }

        return Inertia::render('Student/Story', [
            'story' => [
                'id' => $story->id,
                'type' => $story->type,
                'title' => $story->title,
                'body_html' => $story->body_html,
                'cover_path' => $story->cover_path,
                'youtube_video_id' => $story->youtube_video_id,
                'transcript' => $story->transcript,
                'must_watch_pct' => $story->must_watch_pct,
            ],
            'stage' => [
                'id' => $stage->id,
                'title' => $stage->title,
                'order' => $stage->order,
            ],
            'level' => $stage->level ? [
                'id' => $stage->level->id,
                'title' => $stage->level->title,
                'order' => $stage->level->order,
            ] : null,
            'bestStars' => UnlockService::bestStars($user->id, $stage->id),
            'questionCount' => $story->questions->count(),
        ]);
    }
}
