<?php

use App\Models\ClassRoom;
use App\Models\User;

it('teacher creates class and imports csv roster', function () {
    $t = User::factory()->create(['role' => 'teacher']);
    $this->actingAs($t)->post('/teacher/classes', ['name' => 'Grade 6 - A', 'section' => 'A', 'school_year' => '2026-2027'])->assertRedirect();
    expect(ClassRoom::where('teacher_id', $t->id)->count())->toBe(1);
    $class = ClassRoom::first();
    // CSV import 2 rows
    $csv = "display_name,username\nJuan Dela Cruz,\nMaria Santos,maria.s\n";
    file_put_contents($p = tempnam(sys_get_temp_dir(), 'roster').'.csv', $csv);
    $this->actingAs($t)->post("/teacher/classes/{$class->id}/roster-import", ['file' => new \Illuminate\Http\UploadedFile($p, 'roster.csv', 'text/csv', null, true)])->assertRedirect();
    expect($class->fresh()->students()->count())->toBe(2);
});

it('teacher cannot reset student outside own class', function () {
    $t1 = User::factory()->create(['role' => 'teacher']);
    $t2 = User::factory()->create(['role' => 'teacher']);
    $s = User::factory()->create(['role' => 'student']);
    $c2 = ClassRoom::create(['teacher_id' => $t2->id, 'name' => 'B', 'school_year' => '2026-2027', 'code' => 'XX99YY']);
    $c2->students()->attach($s->id);
    $this->actingAs($t1)->post("/teacher/students/{$s->id}/reset-link")->assertForbidden();
});
