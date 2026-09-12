<?php

use App\Models\Level;
use App\Models\Stage;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
});

function seedUnlockStory(): array
{
    $level = Level::create([
        'title' => 'L1',
        'order' => 1,
        'is_published' => true,
    ]);

    $s1 = Stage::create([
        'level_id' => $level->id,
        'title' => 'S1',
        'order' => 1,
        'required_stars_to_unlock' => 1,
        'is_published' => true,
    ]);

    $s2 = Stage::create([
        'level_id' => $level->id,
        'title' => 'S2',
        'order' => 2,
        'required_stars_to_unlock' => 1,
        'is_published' => true,
    ]);

    $story1 = Story::create([
        'stage_id' => $s1->id,
        'type' => 'text',
        'title' => 'Story 1',
        'body_html' => '<p>Hello</p>',
    ]);

    $story2 = Story::create([
        'stage_id' => $s2->id,
        'type' => 'text',
        'title' => 'Story 2',
        'body_html' => '<p>World</p>',
    ]);

    return [$level, $s1, $s2, $story1, $story2];
}

it('student cannot open locked stage via url', function () {
    $student = User::factory()->create(['role' => 'student']);
    [$level, $s1, $s2, $story1, $story2] = seedUnlockStory();

    $this->actingAs($student)->get("/stories/{$story2->id}")->assertForbidden();
});

it('first stage open, second unlocks after 1 star', function () {
    $student = User::factory()->create(['role' => 'student']);
    [$level, $s1, $s2, $story1, $story2] = seedUnlockStory();

    // First stage is always open.
    $this->actingAs($student)->get("/stories/{$story1->id}")->assertOk();

    // Insert a 1-star attempt directly on S1.
    \DB::table('attempts')->insert([
        'id' => (string) Str::uuid(),
        'student_id' => $student->id,
        'stage_id' => $s1->id,
        'story_id' => $story1->id,
        'total' => 5,
        'correct_count' => 2,
        'pct' => 40.00,
        'stars' => 1,
        'exp_earned' => 30,
        'answers' => json_encode([]),
        'duration_sec' => 60,
        'idempotency_key' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($student)->get("/stories/{$story2->id}")->assertOk();
});
