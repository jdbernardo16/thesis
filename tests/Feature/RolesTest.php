<?php

use App\Models\User;

it('teacher cannot view admin users page', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $this->actingAs($teacher)->get('/admin/users')->assertForbidden();
});

it('teacher can generate reset link for own student', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $student = User::factory()->create(['role' => 'student']);
    $class = \App\Models\ClassRoom::create(['teacher_id' => $teacher->id, 'name' => 'A', 'school_year' => '2026-2027', 'code' => 'AA11BB']);
    $class->students()->attach($student->id);
    $this->actingAs($teacher)->post("/teacher/students/{$student->id}/reset-link")->assertRedirect();
    $row = \DB::table('password_reset_links')->where('user_id', $student->id)->first();
    expect($row)->not->toBeNull();
    expect(new \DateTime($row->expires_at) > new \DateTime())->toBeTrue();
});

it('student cannot generate reset link', function () {
    $student = User::factory()->create(['role' => 'student']);
    $other = User::factory()->create(['role' => 'student']);
    $this->actingAs($student)->post("/teacher/students/{$other->id}/reset-link")->assertForbidden();
});

it('guest is redirected when posting reset link', function () {
    $student = User::factory()->create(['role' => 'student']);
    $this->post("/teacher/students/{$student->id}/reset-link")->assertRedirect();
});
