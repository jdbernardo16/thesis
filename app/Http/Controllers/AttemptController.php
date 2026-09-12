<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Stage;
use App\Models\StudentProgress;
use App\Services\ExpCalculator;
use App\Services\GradingService;
use App\Services\StarCalculator;
use App\Services\UnlockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AttemptController extends Controller
{
    public function store(Request $request, Stage $stage)
    {
        $user = $request->user();
        $stage->loadMissing(['level', 'story.questions']);

        abort_if(! $stage->story, 422, 'This stage has no quiz yet.');

        // Unlock gate (admins/teachers bypass for preview).
        if ($user && $user->role === 'student') {
            abort_unless(
                UnlockService::stageUnlocked($user->id, $stage),
                403,
                'Clear previous stage first.'
            );
        }

        $data = $request->validate([
            'answers' => 'required|array',
            'duration_sec' => 'nullable|integer|min:0|max:86400',
            'idempotency_key' => 'required|string|max:64',
        ]);

        // Idempotency: duplicate key returns the existing attempt's result.
        $existing = Attempt::where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            abort_if(
                $existing->student_id !== $user->id,
                422,
                'Duplicate submission key.'
            );

            return $this->resultResponse($existing, false);
        }

        $questions = $stage->story->questions()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        abort_if($questions->isEmpty(), 422, 'This quiz has no active questions.');

        // Validate answers shape: keys must belong to this story's quiz.
        $validIds = $questions->pluck('id')->map(fn ($id) => (string) $id)->all();
        foreach (array_keys($data['answers']) as $key) {
            abort_unless(in_array((string) $key, $validIds, true), 422, 'Unknown question in answers.');
        }

        // Server-side grading — any client-supplied score/stars/correct_count is ignored.
        $priorBest = UnlockService::bestStars($user->id, $stage->id);

        $results = [];
        $correct = 0;
        foreach ($questions as $q) {
            $given = $data['answers'][(string) $q->id] ?? $data['answers'][$q->id] ?? null;
            $ok = GradingService::grade($q, $given);
            if ($ok) {
                $correct++;
            }
            $results[] = ['question_id' => $q->id, 'correct' => $ok, 'given' => $given];
        }

        $total = $questions->count();
        $pct = $total > 0 ? round($correct / $total * 100, 2) : 0.0;
        $stars = StarCalculator::forPct((float) $pct);
        $repeatPerfect = $priorBest === 3;
        $exp = ExpCalculator::forAttempt($correct, $total, $stars, $repeatPerfect);
        $isBest = $stars > $priorBest;

        $attempt = DB::transaction(function () use ($user, $stage, $data, $results, $total, $correct, $pct, $stars, $exp) {
            $attempt = Attempt::create([
                'student_id' => $user->id,
                'stage_id' => $stage->id,
                'story_id' => $stage->story->id,
                'total' => $total,
                'correct_count' => $correct,
                'pct' => $pct,
                'stars' => $stars,
                'exp_earned' => $exp,
                'answers' => $results,
                'duration_sec' => $data['duration_sec'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
            ]);

            $this->recomputeProgress($user->id, (int) $stage->level_id);

            return $attempt;
        });

        return $this->resultResponse($attempt, $isBest);
    }

    public function show(Request $request, Attempt $attempt)
    {
        $user = $request->user();

        if ($user && $user->role === 'student') {
            abort_unless($attempt->student_id === $user->id, 403);
        }

        $priorBest = Attempt::where('student_id', $attempt->student_id)
            ->where('stage_id', $attempt->stage_id)
            ->where('id', '!=', $attempt->id)
            ->max('stars') ?? 0;

        return $this->resultResponse($attempt, $attempt->stars > (int) $priorBest);
    }

    protected function resultResponse(Attempt $attempt, bool $isBest)
    {
        $attempt->loadMissing(['stage.level', 'story', 'stage.story']);

        $questions = $attempt->story
            ? $attempt->story->questions()->orderBy('order')->get()->keyBy('id')
            : collect();

        $stored = $attempt->answers ?? [];
        $review = [];
        foreach ($stored as $row) {
            $q = $questions->get($row['question_id'] ?? null);
            $review[] = [
                'question_id' => $row['question_id'] ?? null,
                'stem' => $q?->stem,
                'type' => $q ? GradingService::normalizeType((string) $q->type) : null,
                'given' => $row['given'] ?? null,
                'is_correct' => (bool) ($row['correct'] ?? false),
                'correct_answer' => $q ? self::correctAnswerFor($q) : null,
                'explanation' => $q?->explanation,
            ];
        }

        $bestStars = UnlockService::bestStars($attempt->student_id, $attempt->stage_id);

        return Inertia::render('Student/Result', [
            'attempt' => [
                'id' => $attempt->id,
                'correct_count' => $attempt->correct_count,
                'total' => $attempt->total,
                'pct' => (float) $attempt->pct,
                'stars' => $attempt->stars,
                'exp' => $attempt->exp_earned,
            ],
            'is_best' => $isBest,
            'bestStars' => $bestStars,
            'review' => $review,
            'stage' => $attempt->stage ? [
                'id' => $attempt->stage->id,
                'title' => $attempt->stage->title,
                'order' => $attempt->stage->order,
            ] : null,
            'story' => $attempt->story ? [
                'id' => $attempt->story->id,
                'title' => $attempt->story->title,
            ] : null,
        ]);
    }

    protected static function correctAnswerFor($question): mixed
    {
        $payload = $question->payload ?? [];
        $type = GradingService::normalizeType((string) $question->type);

        return match ($type) {
            'mc_single' => $payload['correct_option_id'] ?? $payload['answer'] ?? null,
            'true_false' => (bool) ($payload['correct'] ?? $payload['answer'] ?? false),
            'ordering' => array_values((array) ($payload['correct_order'] ?? [])),
            'fill_blank' => $payload['acceptable_answers'] ?? $payload['answers'] ?? [],
            default => null,
        };
    }

    protected function recomputeProgress(int $studentId, int $levelId): void
    {
        $bestPerStage = Attempt::where('student_id', $studentId)
            ->selectRaw('stage_id, MAX(stars) m')
            ->groupBy('stage_id')
            ->pluck('m', 'stage_id');

        StudentProgress::updateOrCreate(
            ['student_id' => $studentId],
            [
                'total_stars' => (int) $bestPerStage->sum(),
                'total_exp' => (int) Attempt::where('student_id', $studentId)->sum('exp_earned'),
                'stages_cleared' => (int) $bestPerStage->filter(fn ($m) => $m >= 1)->count(),
                'stages_perfect' => (int) $bestPerStage->filter(fn ($m) => $m >= 3)->count(),
                'current_level_id' => $levelId,
                'last_active_at' => now(),
            ]
        );
    }
}
