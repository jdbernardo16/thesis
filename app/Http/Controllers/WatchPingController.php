<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WatchPingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'story_id' => 'required|exists:stories,id',
            'pct' => 'required|integer|min:0|max:100',
        ]);

        // Lightweight progress ping — no persistence in MVP beyond validation.
        // Quiz gating is enforced client-side + server-side via must_watch_pct
        // acknowledgement in Task 6; pings here enable future analytics.

        return response()->json(['ok' => true]);
    }
}
