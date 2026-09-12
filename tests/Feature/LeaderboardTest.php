<?php

use App\Models\ClassRoom;
use App\Models\StudentProgress;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('isolates classes and orders by stars then exp', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $classA = ClassRoom::create([
        'teacher_id' => $teacher->id,
        'name' => 'Class A',
        'school_year' => '2026-2027',
        'code' => 'AAAAAA',
        'leaderboard_visible' => true,
    ]);
    $classB = ClassRoom::create([
        'teacher_id' => $teacher->id,
        'name' => 'Class B',
        'school_year' => '2026-2027',
        'code' => 'BBBBBB',
        'leaderboard_visible' => true,
    ]);

    $s1 = User::factory()->create(['role' => 'student', 'name' => 'Ana Santos']);
    $s2 = User::factory()->create(['role' => 'student', 'name' => 'Ben Cruz']);
    $s3 = User::factory()->create(['role' => 'student', 'name' => 'Cara Reyes']);
    $outsider = User::factory()->create(['role' => 'student', 'name' => 'Dan Other']);

    $classA->students()->attach([$s1->id, $s2->id, $s3->id]);
    $classB->students()->attach([$outsider->id]);

    StudentProgress::create(['student_id' => $s1->id, 'total_stars' => 5, 'total_exp' => 100]);
    StudentProgress::create(['student_id' => $s2->id, 'total_stars' => 5, 'total_exp' => 200]);
    StudentProgress::create(['student_id' => $s3->id, 'total_stars' => 3, 'total_exp' => 500]);
    StudentProgress::create(['student_id' => $outsider->id, 'total_stars' => 99, 'total_exp' => 9999]);

    $res = $this->actingAs($s1)->get("/classes/{$classA->id}/leaderboard");

    $res->assertOk();
    $entries = $res->viewData('page')['props']['entries'] ?? null;
    // Inertia props are nested; fall back to JSON decode of page when needed.
    if ($entries === null) {
        $page = $res->viewData('page');
        $entries = $page['props']['entries'] ?? [];
    }

    $ids = array_column($entries, 'student_id');

    expect($ids)->toBe([$s2->id, $s1->id, $s3->id])
        ->and($ids)->not->toContain($outsider->id);
});

it('hidden leaderboard blocks students but allows teacher preview', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $class = ClassRoom::create([
        'teacher_id' => $teacher->id,
        'name' => 'Hidden Class',
        'school_year' => '2026-2027',
        'code' => 'CCCCCC',
        'leaderboard_visible' => false,
    ]);
    $student = User::factory()->create(['role' => 'student']);
    $class->students()->attach($student->id);

    $this->actingAs($student)
        ->get("/classes/{$class->id}/leaderboard")
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get("/classes/{$class->id}/leaderboard")
        ->assertOk();
});
