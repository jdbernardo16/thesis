<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\Request;

class LevelController extends Controller
{
    public function store(Request $r)
    {
        abort_unless($r->user() && $r->user()->role === 'admin', 403);

        $d = $r->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'required|integer|unique:levels,order',
            'cover_path' => 'nullable|string|max:255',
            'required_total_stars_to_unlock' => 'nullable|integer|min:0',
            'badge_name' => 'nullable|string|max:255',
        ]);

        $level = Level::create($d);

        return response()->json($level, 201);
    }

    public function publish(Request $r, Level $level)
    {
        abort_unless($r->user() && $r->user()->role === 'admin', 403);

        $publishedStages = $level->stages()->where('is_published', true)->count();
        if ($publishedStages < 1) {
            abort(422, 'Level needs at least one published stage.');
        }

        $level->update(['is_published' => true]);

        return response()->json($level->fresh());
    }
}
