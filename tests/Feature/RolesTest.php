<?php

use App\Models\User;

it('teacher cannot view admin users page', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $this->actingAs($teacher)->get('/admin/users')->assertForbidden();
});

it('teacher can generate reset link for own student', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $student = User::factory()->create(['role' => 'student']);
    $this->actingAs($teacher)->post("/teacher/students/{$student->id}/reset-link")->assertRedirect();
});
