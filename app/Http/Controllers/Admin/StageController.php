<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stage;
use Illuminate\Http\Request;

class StageController extends Controller
{
    public function store(Request $r)
    {
        abort_unless($r->user() && $r->user()->role === 'admin', 403);

        $d = $r->validate([
            'level_id' => 'required|exists:levels,id',
            'title' => 'required|string|max:255',
            'order' => 'required|integer|min:1',
            'required_stars_to_unlock' => 'nullable|integer|min:0',
            'is_pretest' => 'nullable|boolean',
            'is_posttest' => 'nullable|boolean',
            'difficulty_tag' => 'nullable|string|max:50',
            'readability_note' => 'nullable|string',
            'estimated_minutes' => 'nullable|integer|min:1',
        ]);

        $stage = Stage::create($d);

        return response()->json($stage, 201);
    }

    public function publish(Request $r, Stage $stage)
    {
        abort_unless($r->user() && $r->user()->role === 'admin', 403);

        $story = $stage->story;
        if (! $story) {
            abort(422, 'Stage needs a story before publishing.');
        }

        $activeCount = $story->questions()->where('is_active', true)->count();
        if ($activeCount < 5) {
            abort(422, 'Stage needs at least 5 active questions.');
        }

        $stage->update(['is_published' => true]);

        return response()->json($stage->fresh());
    }
}
