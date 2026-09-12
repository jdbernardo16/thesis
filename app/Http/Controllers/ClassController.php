<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClassController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'teacher']), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'section' => 'nullable|string|max:255',
            'school_year' => 'required|string|max:255',
        ]);

        do {
            $code = strtoupper(Str::random(6));
        } while (ClassRoom::where('code', $code)->exists());

        $class = ClassRoom::create([
            'teacher_id' => $user->id,
            'name' => $validated['name'],
            'section' => $validated['section'] ?? null,
            'school_year' => $validated['school_year'],
            'code' => $code,
        ]);

        return redirect("/teacher/classes/{$class->id}");
    }

    public function show(Request $request, ClassRoom $class)
    {
        Gate::authorize('view', $class);

        $class->load('students');

        if (class_exists(Inertia::class)) {
            return Inertia::render('Teacher/ClassShow', [
                'class' => $class,
            ]);
        }

        return redirect()->back()->with('roster', $class->students);
    }
}
