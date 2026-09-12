<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RosterImportController extends Controller
{
    public function store(Request $request, ClassRoom $class)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'teacher']), 403);
        Gate::authorize('view', $class);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $path = $request->file('file')->getRealPath();
        $rows = $this->parseCsv($path);

        if (empty($rows)) {
            return back()->withErrors(['file' => 'CSV is empty.']);
        }

        // Dry-run validation first (no partial commit)
        $errors = [];
        $seenUsernames = [];
        $toCreate = [];
        $seq = $class->students()->count() + 1;

        foreach ($rows as $index => $row) {
            $line = $index + 2; // +1 for 0-index, +1 for header
            $displayName = trim($row['display_name'] ?? '');
            $username = trim($row['username'] ?? '');

            if ($displayName === '') {
                $errors["row_{$line}"] = "Row {$line}: display_name is required.";
                continue;
            }

            if ($username === '') {
                // auto-generate <CODE>-<seq>
                do {
                    $username = $class->code.'-'.str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
                    $seq++;
                } while (
                    User::where('username', $username)->exists()
                    || in_array($username, $seenUsernames)
                );
            } else {
                if (in_array($username, $seenUsernames) || User::where('username', $username)->exists()) {
                    $errors["row_{$line}"] = "Row {$line}: username already taken.";
                    continue;
                }
            }

            $seenUsernames[] = $username;
            $toCreate[] = [
                'display_name' => $displayName,
                'username' => $username,
                'line' => $line,
            ];
        }

        if (! empty($errors)) {
            return back()->withErrors($errors);
        }

        try {
            DB::transaction(function () use ($toCreate, $class) {
                foreach ($toCreate as $item) {
                    $email = $this->uniqueEmailFor($item['username']);

                    $student = User::create([
                        'name' => $item['display_name'],
                        'email' => $email,
                        'username' => $item['username'],
                        'password' => Str::random(10),
                        'role' => 'student',
                        'must_change_password' => true,
                        'active' => true,
                    ]);

                    // Guard: only student role may be created via import
                    if (! in_array($student->role, ['student'])) {
                        throw new \RuntimeException("Row {$item['line']}: invalid role.");
                    }

                    $class->students()->syncWithoutDetaching([$student->id]);
                }
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Import failed: '.$e->getMessage()]);
        }

        return redirect("/teacher/classes/{$class->id}");
    }

    /**
     * @return array<int, array{display_name?: string, username?: string}>
     */
    protected function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) === false) {
            return $rows;
        }

        $header = null;
        while (($data = fgetcsv($handle)) !== false) {
            // Skip fully empty lines
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }

            if ($header === null) {
                $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $data);
                // Detect header row
                if (in_array('display_name', $normalized)) {
                    $header = $normalized;
                    continue;
                }
                // No header — assume display_name,username order
                $header = ['display_name', 'username'];
                // fall through to treat current line as data
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $data[$i] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    protected function uniqueEmailFor(string $username): string
    {
        $slug = Str::slug($username) !== '' ? Str::slug($username) : Str::lower(Str::random(8));
        $base = $slug.'@school.local';
        $email = $base;
        $i = 1;
        while (User::where('email', $email)->exists()) {
            $email = $slug.'+'.$i.'@school.local';
            $i++;
        }

        return $email;
    }
}
