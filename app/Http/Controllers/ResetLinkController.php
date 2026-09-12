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
        abort_unless($teacher->role === 'admin' || $teacher->role === 'teacher', 403);

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
