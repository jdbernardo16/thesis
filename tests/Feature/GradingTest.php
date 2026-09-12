<?php

use App\Models\Attempt;
use App\Models\Level;
use App\Models\Question;
use App\Models\Stage;
use App\Models\Story;
use App\Models\User;
use App\Services\ExpCalculator;
use App\Services\StarCalculator;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
});

function seedTwoMcStory(): array
{
    $student = User::factory()->create(['role' => 'student']);

    $level = Level::create(['title' => 'L1', 'order' => 1, 'is_published' => true]);
    $stage = Stage::create([
        'level_id' => $level->id,
        'title' => 'S1',
        'order' => 1,
        'required_stars_to_unlock' => 1,
        'is_published' => true,
    ]);
    $story = Story::create([
        'stage_id' => $stage->id,
        'type' => 'text',
        'title' => 'Story 1',
        'body_html' => '<p>Hello</p>',
    ]);

    $q1 = Question::create([
        'story_id' => $story->id,
        'order' => 1,
        'type' => 'mc_single',
        'stem' => 'Pick A',
        'payload' => ['options' => ['A', 'B'], 'correct_option_id' => 'A'],
        'is_active' => true,
    ]);
    $q2 = Question::create([
        'story_id' => $story->id,
        'order' => 2,
        'type' => 'mc_single',
        'stem' => 'Pick B',
        'payload' => ['options' => ['A', 'B'], 'correct_option_id' => 'B'],
        'is_active' => true,
    ]);

    return [$student, $stage, $story, $q1, $q2];
}

it('star tiers', fn () => expect([
    StarCalculator::forPct(20),
    StarCalculator::forPct(30),
    StarCalculator::forPct(65),
    StarCalculator::forPct(95),
])->toBe([0, 1, 2, 3]));

it('exp formula', fn () => expect(ExpCalculator::forAttempt(7, 10, 2, false))->toBe(100));

it('rejects client score tampering', function () {
    [$student, $stage, $story, $q1, $q2] = seedTwoMcStory();

    // 1 of 2 correct, but client forges a perfect score.
    $this->actingAs($student)->post("/stages/{$stage->id}/attempts", [
        'answers' => [$q1->id => 'A', $q2->id => 'A'], // Q2 correct is B
        'score' => 100,
        'stars' => 3,
        'correct_count' => 2,
        'duration_sec' => 30,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    $attempt = Attempt::where('student_id', $student->id)->latest()->first();
    expect($attempt->correct_count)->toBe(1)
        ->and($attempt->stars)->toBe(1);
});

it('retry never lowers stars, repeat-perfect gives reduced exp', function () {
    [$student, $stage, $story, $q1, $q2] = seedTwoMcStory();

    // Perfect first attempt: 2 correct -> 100% -> 3 stars, exp = 2*10+50 = 70.
    $key1 = (string) Str::uuid();
    $this->actingAs($student)->post("/stages/{$stage->id}/attempts", [
        'answers' => [$q1->id => 'A', $q2->id => 'B'],
        'duration_sec' => 20,
        'idempotency_key' => $key1,
    ])->assertOk();

    $first = Attempt::where('idempotency_key', $key1)->first();
    expect($first->stars)->toBe(3)->and($first->exp_earned)->toBe(70);

    // Repeat perfect: same 3 stars but reduced exp (intdiv(70,5) = 14).
    $key2 = (string) Str::uuid();
    $this->actingAs($student)->post("/stages/{$stage->id}/attempts", [
        'answers' => [$q1->id => 'A', $q2->id => 'B'],
        'duration_sec' => 15,
        'idempotency_key' => $key2,
    ])->assertOk();

    $repeat = Attempt::where('idempotency_key', $key2)->first();
    expect($repeat->stars)->toBe(3)->and($repeat->exp_earned)->toBe(14);

    // Worse retry: best is kept in progress.
    $this->actingAs($student)->post("/stages/{$stage->id}/attempts", [
        'answers' => [$q1->id => 'A', $q2->id => 'A'],
        'duration_sec' => 10,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    $progress = \App\Models\StudentProgress::find($student->id);
    expect($progress->total_stars)->toBe(3);
});

it('duplicate idempotency key returns existing attempt without double-counting exp', function () {
    [$student, $stage, $story, $q1, $q2] = seedTwoMcStory();
    $key = (string) Str::uuid();

    $payload = [
        'answers' => [$q1->id => 'A', $q2->id => 'B'],
        'duration_sec' => 20,
        'idempotency_key' => $key,
    ];

    $this->actingAs($student)->post("/stages/{$stage->id}/attempts", $payload)->assertOk();
    $this->actingAs($student)->post("/stages/{$stage->id}/attempts", $payload)->assertOk();

    expect(Attempt::where('idempotency_key', $key)->count())->toBe(1)
        ->and(\App\Models\StudentProgress::find($student->id)->total_exp)->toBe(70);
});
