<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function store(Request $r)
    {
        abort_unless($r->user() && $r->user()->role === 'admin', 403);

        $d = $r->validate([
            'story_id' => 'required|exists:stories,id',
            'order' => [
                'required', 'integer', 'min:1',
                Rule::unique('questions', 'order')->where(fn ($q) => $q->where('story_id', $r->input('story_id'))),
            ],
            'type' => 'required|in:mc_single,true_false,ordering,fill_blank,mcq',
            'stem' => 'required|string',
            'payload' => 'required|array',
            'points' => 'nullable|integer|min:1',
            'explanation' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        // Normalize legacy 'mcq' alias to plan type.
        if ($d['type'] === 'mcq') {
            $d['type'] = 'mc_single';
        }

        $question = Question::create([
            'story_id' => $d['story_id'],
            'order' => $d['order'],
            'type' => $d['type'],
            'stem' => $d['stem'],
            'payload' => $d['payload'],
            'points' => $d['points'] ?? 1,
            'explanation' => $d['explanation'] ?? null,
            'is_active' => $d['is_active'] ?? true,
        ]);

        return response()->json($question, 201);
    }
}
