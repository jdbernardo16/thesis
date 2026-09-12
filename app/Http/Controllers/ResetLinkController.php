<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetLinkController extends Controller
{
    public function store(Request $r, User $student)
    {
        $teacher = $r->user();
        abort_unless($teacher && in_array($teacher->role, ['admin', 'teacher']), 403);

        // Only students may receive a reset link.
        abort_unless($student->role === 'student', 403);

        // Teachers may only reset students enrolled in one of their classes.
        // Admins bypass the enrollment check.
        if ($teacher->role !== 'admin') {
            $enrolled = \App\Models\ClassRoom::where('teacher_id', $teacher->id)
                ->whereHas('students', fn ($q) => $q->where('users.id', $student->id))
                ->exists();
            abort_unless($enrolled, 403);
        }

        $raw = Str::random(40);
        \DB::table('password_reset_links')->insert([
            'user_id' => $student->id,
            'token_hash' => Hash::make($raw),
            'expires_at' => now()->addHour(),
            'created_by' => $teacher->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('reset_raw', $raw);
    }
}
