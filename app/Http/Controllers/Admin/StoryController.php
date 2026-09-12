<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public static function toVideoId(string $url): ?string
    {
        if (preg_match('/(?:v=|youtu\.be\/|shorts\/)([\w-]{11})/', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public function store(Request $r)
    {
        abort_unless($r->user() && $r->user()->role === 'admin', 403);

        $d = $r->validate([
            'stage_id' => 'required|exists:stages,id|unique:stories,stage_id',
            'type' => 'required|in:text,youtube',
            'title' => 'required|string|max:255',
            'body_html' => 'nullable|string',
            'cover_path' => 'nullable|string|max:255',
            'youtube_url' => 'nullable|string|max:500',
            'youtube_video_id' => 'nullable|string|size:11',
            'transcript' => 'nullable|string',
            'must_watch_pct' => 'nullable|integer|min:1|max:100',
        ]);

        $videoId = $d['youtube_video_id'] ?? null;
        if (! $videoId && ! empty($d['youtube_url'])) {
            $videoId = self::toVideoId($d['youtube_url']);
        }

        $story = Story::create([
            'stage_id' => $d['stage_id'],
            'type' => $d['type'],
            'title' => $d['title'],
            'body_html' => $d['body_html'] ?? null,
            'cover_path' => $d['cover_path'] ?? null,
            'youtube_video_id' => $videoId,
            'transcript' => $d['transcript'] ?? null,
            'must_watch_pct' => $d['must_watch_pct'] ?? 80,
        ]);

        return response()->json($story, 201);
    }
}
