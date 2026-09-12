<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeaderboardController extends Controller
{
    public function show(Request $request, ClassRoom $class)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $isAdmin = $user->role === 'admin';
        $isOwner = $user->role === 'teacher' && (int) $class->teacher_id === (int) $user->id;
        $isStudent = $user->role === 'student';

        if ($isStudent) {
            $enrolled = DB::table('class_student')
                ->where('class_id', $class->id)
                ->where('student_id', $user->id)
                ->exists();
            abort_unless($enrolled, 403, 'You are not enrolled in this class.');

            if (! $class->leaderboard_visible) {
                abort(403, 'Leaderboard is hidden by your teacher.');
            }
        } elseif (! $isAdmin && ! $isOwner) {
            abort(403);
        }

        $scope = $request->query('scope', 'all') === 'week' ? 'week' : 'all';

        $studentIds = DB::table('class_student')
            ->where('class_id', $class->id)
            ->pluck('student_id')
            ->all();

        $note = null;
        $entries = [];

        if ($scope === 'week') {
            $entries = $this->weeklyEntries($studentIds, $note);
        } else {
            $entries = $this->allTimeEntries($studentIds);
        }

        // Rank: 1-indexed position after ordering.
        foreach ($entries as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        unset($row);

        $isPreview = ! $class->leaderboard_visible && ($isAdmin || $isOwner);

        return Inertia::render('Student/Leaderboard', [
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'section' => $class->section,
                'leaderboard_visible' => (bool) $class->leaderboard_visible,
            ],
            'scope' => $scope,
            'entries' => $entries,
            'note' => $note,
            'is_preview' => $isPreview,
        ]);
    }

    /**
     * @param  array<int>  $studentIds
     * @return array<int, array>
     */
    protected function allTimeEntries(array $studentIds): array
    {
        if (empty($studentIds)) {
            return [];
        }

        $rows = User::whereIn('users.id', $studentIds)
            ->leftJoin('student_progress as sp', 'sp.student_id', '=', 'users.id')
            ->orderByDesc(DB::raw('COALESCE(sp.total_stars,0)'))
            ->orderByDesc(DB::raw('COALESCE(sp.total_exp,0)'))
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                DB::raw('COALESCE(sp.total_stars,0) as total_stars'),
                DB::raw('COALESCE(sp.total_exp,0) as total_exp'),
            ]);

        return $rows->map(fn ($u) => [
            'student_id' => (int) $u->id,
            'name' => $u->name,
            'initials' => self::initials($u->name),
            'total_stars' => (int) $u->total_stars,
            'total_exp' => (int) $u->total_exp,
        ])->all();
    }

    /**
     * Weekly variant: max-per-stage stars in last 7d + summed exp in window.
     * Falls back to all-time with a note when there is no weekly activity.
     *
     * @param  array<int>  $studentIds
     */
    protected function weeklyEntries(array $studentIds, ?string &$note): array
    {
        $note = null;

        if (empty($studentIds)) {
            return [];
        }

        $since = now()->subDays(7);

        $weeklyAttempts = DB::table('attempts')
            ->whereIn('student_id', $studentIds)
            ->where('created_at', '>=', $since)
            ->count();

        if ($weeklyAttempts === 0) {
            $note = 'No activity in the last 7 days — showing all-time standings.';
            return $this->allTimeEntries($studentIds);
        }

        $maxPerStage = DB::table('attempts')
            ->whereIn('student_id', $studentIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('student_id, stage_id, MAX(stars) as m')
            ->groupBy('student_id', 'stage_id')
            ->get();

        $starsByStudent = [];
        foreach ($maxPerStage as $row) {
            $starsByStudent[$row->student_id] = ($starsByStudent[$row->student_id] ?? 0) + (int) $row->m;
        }

        $expByStudent = DB::table('attempts')
            ->whereIn('student_id', $studentIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('student_id, SUM(exp_earned) as e')
            ->groupBy('student_id')
            ->pluck('e', 'student_id')
            ->all();

        $users = User::whereIn('id', $studentIds)->get(['id', 'name']);

        $entries = $users->map(fn ($u) => [
            'student_id' => (int) $u->id,
            'name' => $u->name,
            'initials' => self::initials($u->name),
            'total_stars' => (int) ($starsByStudent[$u->id] ?? 0),
            'total_exp' => (int) ($expByStudent[$u->id] ?? 0),
        ])->all();

        usort($entries, function ($a, $b) {
            if ($a['total_stars'] !== $b['total_stars']) {
                return $b['total_stars'] <=> $a['total_stars'];
            }
            if ($a['total_exp'] !== $b['total_exp']) {
                return $b['total_exp'] <=> $a['total_exp'];
            }
            return strcmp($a['name'], $b['name']);
        });

        return $entries;
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
