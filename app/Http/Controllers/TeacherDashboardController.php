<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\Level;
use App\Models\Stage;
use App\Models\StudentProgress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeacherDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'teacher']), 403);

        $classes = $user->role === 'admin'
            ? ClassRoom::with('teacher:id,name')->orderBy('id')->get()
            : ClassRoom::where('teacher_id', $user->id)->orderBy('id')->get();

        $totalStages = Stage::where('is_published', true)->count();

        $cards = [];
        $strugglingAll = [];

        // Preload users map for names.
        foreach ($classes as $class) {
            $studentIds = DB::table('class_student')
                ->where('class_id', $class->id)
                ->pluck('student_id')
                ->all();

            $usersById = User::whereIn('id', $studentIds)->get(['id', 'name'])->keyBy('id');
            $progressById = StudentProgress::whereIn('student_id', $studentIds)
                ->get()->keyBy('student_id');
            $avgPctById = empty($studentIds) ? collect() : DB::table('attempts')
                ->whereIn('student_id', $studentIds)
                ->selectRaw('student_id, AVG(pct) as a')
                ->groupBy('student_id')
                ->pluck('a', 'student_id');

            $completions = [];
            $accuracies = [];
            $struggling = [];

            foreach ($studentIds as $sid) {
                $prog = $progressById->get($sid);
                $cleared = (int) ($prog?->stages_cleared ?? 0);
                $completions[] = $totalStages > 0 ? $cleared / $totalStages : 0;

                $avg = isset($avgPctById[$sid]) ? (float) $avgPctById[$sid] : null;
                if ($avg !== null) {
                    $accuracies[] = $avg;
                }

                $lastActive = $prog?->last_active_at;
                $inactive = $lastActive === null || $lastActive->lt(now()->subDays(7));
                $lowAccuracy = $avg !== null && $avg < 50;

                if ($lowAccuracy || $inactive) {
                    $reasons = [];
                    if ($lowAccuracy) {
                        $reasons[] = 'low_accuracy';
                    }
                    if ($inactive) {
                        $reasons[] = 'inactive';
                    }
                    $entry = [
                        'class_id' => (int) $class->id,
                        'class_name' => $class->name,
                        'student_id' => (int) $sid,
                        'name' => $usersById->get($sid)?->name ?? "Student #{$sid}",
                        'avg_accuracy' => $avg !== null ? round($avg, 1) : null,
                        'last_active_at' => $lastActive?->toIso8601String(),
                        'reasons' => $reasons,
                    ];
                    $struggling[] = $entry;
                    $strugglingAll[] = $entry;
                }
            }

            $cards[] = [
                'id' => (int) $class->id,
                'name' => $class->name,
                'section' => $class->section,
                'school_year' => $class->school_year,
                'code' => $class->code,
                'teacher_name' => $class->relationLoaded('teacher') ? $class->teacher?->name : null,
                'students_count' => count($studentIds),
                'avg_completion' => count($completions) ? round(array_sum($completions) / count($completions), 3) : 0,
                'avg_accuracy' => count($accuracies) ? round(array_sum($accuracies) / count($accuracies), 1) : 0,
                'struggling_count' => count($struggling),
            ];
        }

        return Inertia::render('Teacher/Dashboard', [
            'classes' => $cards,
            'struggling' => $strugglingAll,
            'total_stages' => $totalStages,
        ]);
    }

    public function show(Request $request, ClassRoom $class)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'teacher']), 403);

        if ($user->role !== 'admin') {
            abort_unless((int) $class->teacher_id === (int) $user->id, 403);
        }

        $class->load('teacher:id,name');

        $stages = Stage::query()
            ->join('levels', 'levels.id', '=', 'stages.level_id')
            ->where('stages.is_published', true)
            ->orderBy('levels.order')
            ->orderBy('stages.order')
            ->select('stages.*')
            ->with('level:id,title,order')
            ->get();

        $stagePayload = $stages->map(fn ($s) => [
            'id' => (int) $s->id,
            'title' => $s->title,
            'order' => (int) $s->order,
            'level_id' => (int) $s->level_id,
            'level_title' => $s->level?->title,
            'level_order' => $s->level ? (int) $s->level->order : null,
        ])->all();

        $studentIds = DB::table('class_student')
            ->where('class_id', $class->id)
            ->pluck('student_id')
            ->all();

        $users = User::whereIn('id', $studentIds)->orderBy('name')->get(['id', 'name']);
        $progressById = StudentProgress::whereIn('student_id', $studentIds)->get()->keyBy('student_id');
        $avgPctById = empty($studentIds) ? collect() : DB::table('attempts')
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, AVG(pct) as a')
            ->groupBy('student_id')
            ->pluck('a', 'student_id');

        $bestRows = empty($studentIds) ? collect() : DB::table('attempts')
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, stage_id, MAX(stars) as m')
            ->groupBy('student_id', 'stage_id')
            ->get();

        $bestByStudentStage = [];
        foreach ($bestRows as $r) {
            $bestByStudentStage[$r->student_id][$r->stage_id] = (int) $r->m;
        }

        // Ordered published levels for derived current-level.
        $orderedLevels = Level::orderBy('order')->get(['id', 'title', 'order']);

        $roster = [];
        foreach ($users as $u) {
            $prog = $progressById->get($u->id);
            $avg = isset($avgPctById[$u->id]) ? round((float) $avgPctById[$u->id], 1) : null;
            $lastActive = $prog?->last_active_at;

            $starsByStage = [];
            foreach ($stages as $s) {
                $starsByStage[$s->id] = (int) ($bestByStudentStage[$u->id][$s->id] ?? 0);
            }

            $derived = $this->derivedCurrentLevel($starsByStage, $stages, $orderedLevels);

            $inactive = $lastActive === null || $lastActive->lt(now()->subDays(7));
            $lowAccuracy = $avg !== null && $avg < 50;
            $reasons = [];
            if ($lowAccuracy) {
                $reasons[] = 'low_accuracy';
            }
            if ($inactive) {
                $reasons[] = 'inactive';
            }

            $roster[] = [
                'student_id' => (int) $u->id,
                'name' => $u->name,
                'initials' => self::initials($u->name),
                'total_stars' => (int) ($prog?->total_stars ?? 0),
                'total_exp' => (int) ($prog?->total_exp ?? 0),
                'stages_cleared' => (int) ($prog?->stages_cleared ?? 0),
                'avg_accuracy' => $avg,
                'last_active_at' => $lastActive?->toIso8601String(),
                'current_level' => $derived,
                'stored_level_id' => $prog?->current_level_id,
                'stars_by_stage' => $starsByStage,
                'struggling' => ! empty($reasons),
                'struggling_reasons' => $reasons,
            ];
        }

        return Inertia::render('Teacher/Classes/Show', [
            'class' => [
                'id' => (int) $class->id,
                'name' => $class->name,
                'section' => $class->section,
                'school_year' => $class->school_year,
                'code' => $class->code,
                'leaderboard_visible' => (bool) $class->leaderboard_visible,
                'teacher_name' => $class->teacher?->name,
            ],
            'stages' => $stagePayload,
            'roster' => $roster,
        ]);
    }

    /**
     * Derive current level from max unlocked/progress instead of trusting
     * student_progress.current_level_id (which regresses on replay).
     */
    protected function derivedCurrentLevel(array $starsByStage, $stages, $orderedLevels): ?array
    {
        if ($stages->isEmpty() || $orderedLevels->isEmpty()) {
            return null;
        }

        foreach ($stages as $s) {
            if (($starsByStage[$s->id] ?? 0) < 1) {
                $lvl = $s->level ?? $orderedLevels->firstWhere('id', $s->level_id);
                if (! $lvl) {
                    return null;
                }
                return ['id' => (int) $lvl->id, 'title' => $lvl->title, 'order' => (int) $lvl->order];
            }
        }

        $last = $stages->last();
        $lvl = $last->level ?? $orderedLevels->firstWhere('id', $last->level_id);
        if (! $lvl) {
            return null;
        }

        return ['id' => (int) $lvl->id, 'title' => $lvl->title, 'order' => (int) $lvl->order];
    }

    protected static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name));
        $parts = array_values(array_filter($parts));
        if (empty($parts)) {
            return '?';
        }
        $first = mb_substr($parts[0], 0, 1);
        $second = count($parts) > 1 ? mb_substr($parts[1], 0, 1) : '';
        return mb_strtoupper($first.$second);
    }
}
