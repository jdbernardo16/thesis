<?php

use App\Models\Question;
use App\Models\Story;

it('cannot publish stage without story and 5 questions', function () {
    $a = \App\Models\User::factory()->create(['role' => 'admin']);
    $lvl = \App\Models\Level::create(['title' => 'L1', 'order' => 1, 'is_published' => false]);
    $stage = \App\Models\Stage::create(['level_id' => $lvl->id, 'title' => 'S1', 'order' => 1]);
    $this->actingAs($a)->post("/admin/stages/{$stage->id}/publish")->assertStatus(422);
});

it('publishes stage with story + 5 questions', function () {
    $a = \App\Models\User::factory()->create(['role' => 'admin']);
    $lvl = \App\Models\Level::create(['title' => 'L1', 'order' => 1, 'is_published' => false]);
    $stage = \App\Models\Stage::create(['level_id' => $lvl->id, 'title' => 'S1', 'order' => 1]);
    $story = Story::create([
        'stage_id' => $stage->id,
        'type' => 'text',
        'title' => 'Story 1',
        'body_html' => '<p>Hello</p>',
    ]);
    foreach (range(1, 5) as $i) {
        Question::create([
            'story_id' => $story->id,
            'order' => $i,
            'type' => 'mcq',
            'stem' => "Q{$i}",
            'payload' => ['choices' => ['A', 'B'], 'answer' => 'A'],
            'points' => 1,
            'is_active' => true,
        ]);
    }
    $this->actingAs($a)->post("/admin/stages/{$stage->id}/publish")->assertStatus(200);
    expect($stage->fresh()->is_published)->toBeTrue();
});

it('non-admin cannot create level', function () {
    $t = \App\Models\User::factory()->create(['role' => 'teacher']);
    $this->actingAs($t)->post('/admin/levels', ['title' => 'X', 'order' => 99])->assertForbidden();
});
