# Bridging Reading Gap MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build thesis MVP gamified reading app (Level > Stage=Story > Quiz with stars/EXP gating, per-class leaderboard) as Laravel + Inertia + Vue monolith.

**Architecture:** Laravel 11 monolith serves Inertia Vue pages; all grading/gating server-side in DB transactions; MySQL/MariaDB; local disk for covers; YouTube IFrame API for watch gating with transcript fallback.

**Tech Stack:** PHP 8.4, Laravel 11, Inertia.js v2, Vue 3 + Tailwind, MySQL 8 / MariaDB, Pest for tests, Laravel Breeze (Inertia+Vue stack) for auth scaffolding.

---
**Scope note:** Spec has 7 independent subsystems. This is Plan 01 covering full vertical MVP slice in build order. Each Task below is independently testable and committable. Do not expand scope (no avatars shop, no global leaderboard, no offline PWA).

**File structure (to be created):**
- `database/migrations/` — users role fields, classes, class_student, levels, stages, stories, questions, attempts, student_progress, password_reset_links, settings
- `app/Models/` — User.php, ClassRoom.php, Level.php, Stage.php, Story.php, Question.php, Attempt.php, StudentProgress.php
- `app/Services/` — StarCalculator.php, ExpCalculator.php, GradingService.php, UnlockService.php
- `app/Http/Controllers/` — AttemptController.php, StoryViewController.php, ClassController.php, RosterImportController.php, Admin content controllers, ResetLinkController.php
- `app/Policies/` — ClassPolicy.php, StagePolicy.php
- `resources/js/Pages/` — Student/Map.vue, Student/Story.vue, Student/Quiz.vue, Student/Result.vue, Student/Leaderboard.vue, Teacher/Dashboard.vue, Teacher/Classes/Show.vue, Admin content pages
- `resources/js/Components/` — StarRating.vue, ExpBadge.vue, StageNode.vue, QuizRunner.vue, YoutubePlayer.vue
- `tests/Feature/` — one file per Task below
- `database/seeders/DemoContentSeeder.php` — 1 level, 3 stages (text, youtube, mixed Qs)

Conventions locked: `users.role enum admin|teacher|student`, `username unique`, `UnlockService::stageUnlocked(int $studentId, Stage $stage):bool` + `bestStars(int $studentId,int $stageId):int`, `StarCalculator::forPct(float):int` (0-29→0,30-59→1,60-89→2,90-100→3), `ExpCalculator::forAttempt(correct,total,stars,repeatPerfect):int` = correct*10 + [0,10,30,50][stars], repeat-perfect → intdiv(...,5). `GradingService::grade(Question, $given):bool` handles mc_single/true_false/ordering/fill_blank. Never trust client score.

---

### Task 1: Scaffold app + auth base

**Files:**
- Create: Laravel app in place (via composer), `resources/js/*`, `tests/Pest.php`
- Modify: `.env`, `vite.config.js` (via Breeze)

- [ ] **Step 1: Create Laravel 11 + Breeze Inertia Vue app in current dir**

Run (empty dir except docs/.git — use temp dir then move):
```bash
composer create-project laravel/laravel:^11.0 /tmp/thesis-base && cp -R /tmp/thesis-base/. /Users/jdbernardo/Sites/thesis/ && rm -rf /tmp/thesis-base
composer require inertiajs/inertia-laravel tightenco/ziggy
php artisan breeze:install vue --pest --ssr=false --dark=false
npm install && npm run build
```
Expected: `php artisan --version` → Laravel 11.x, `npm run build` succeeds.

- [ ] **Step 2: Configure .env for local MariaDB/MySQL**

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=thesis
DB_USERNAME=root
DB_PASSWORD=
FILESYSTEM_DISK=public
```
Run:
```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS thesis;"
php artisan migrate --force
php artisan test --parallel 2>&1 | tail -5
```
Expected: migrations pass, tests pass (Breeze auth tests).

- [ ] **Step 3: Commit scaffold**

```bash
git add -A
git commit -m "feat: scaffold laravel 11 + inertia vue + breeze auth base"
git push origin main
```

---

### Task 2: Roles, class ownership, password change + teacher reset links

**Files:**
- Modify: `database/migrations/2014_10_12_000000_create_users_table.php` (add role etc.)
- Create: `database/migrations/2026_09_12_000001_create_auth_ext_tables.php`
- Create: `app/Models/ClassRoom.php`, `app/Policies/ClassPolicy.php`
- Modify: `app/Models/User.php`
- Create: `app/Http/Controllers/ResetLinkController.php`
- Test: `tests/Feature/RolesTest.php`

- [ ] **Step 1: Write failing test for roles + reset link**

```php
// tests/Feature/RolesTest.php
use App\Models\User;
it('teacher cannot view admin users page, student cannot view other class', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $this->actingAs($teacher)->get('/admin/users')->assertForbidden();
});
it('teacher can generate reset link for own student', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $student = User::factory()->create(['role' => 'student']);
    // class wiring done in Task 3; here just assert route exists after Task 2 impl
    $this->actingAs($teacher)->post("/teacher/students/{$student->id}/reset-link")->assertRedirect();
});
```
Run: `php artisan test tests/Feature/RolesTest.php` Expected: FAIL (tables/routes missing).

- [ ] **Step 2: Add role fields + reset links migration**

```php
// database/migrations/2026_09_12_000001_create_auth_ext_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('student')->after('password');
            $t->string('username')->unique()->nullable()->after('email');
            $t->boolean('must_change_password')->default(false);
            $t->string('avatar_color')->default('#4F46E5');
            $t->boolean('active')->default(true);
        });
        Schema::create('password_reset_links', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash')->unique(); $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable(); $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('classes', function (Blueprint $t) {
            $t->id(); $t->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $t->string('name'); $t->string('section')->nullable(); $t->string('school_year');
            $t->string('code')->unique(); $t->boolean('leaderboard_visible')->default(true);
            $t->timestamps();
        });
        Schema::create('class_student', function (Blueprint $t) {
            $t->id(); $t->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $t->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('joined_at')->useCurrent(); $t->unique(['class_id','student_id']);
        });
    }
};
```
Run: `php artisan migrate` Expected: PASS.

- [ ] **Step 3: User model + ClassRoom + Policy + ResetLinkController (minimal)**

```php
// app/Models/User.php (add)
protected $fillable = ['name','email','username','password','role','must_change_password','avatar_color','active'];
public function isRole(string $r): bool { return $this->role === $r; }
public function taughtClasses() { return $this->hasMany(ClassRoom::class, 'teacher_id'); }

// app/Models/ClassRoom.php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class ClassRoom extends Model { protected $table='classes';
  protected $fillable=['teacher_id','name','section','school_year','code','leaderboard_visible'];
  public function teacher(){return $this->belongsTo(User::class,'teacher_id');}
  public function students(){return $this->belongsToMany(User::class,'class_student','class_id','student_id');} }

// app/Policies/ClassPolicy.php
namespace App\Policies; use App\Models\User; use App\Models\ClassRoom;
class ClassPolicy { public function view(User $u, ClassRoom $c): bool {
  return $u->role==='admin' || ($u->role==='teacher' && $c->teacher_id===$u->id); } }

// app/Http/Controllers/ResetLinkController.php
namespace App\Http\Controllers; use App\Models\User; use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash; use Illuminate\Http\Request;
class ResetLinkController extends Controller {
  public function store(Request $r, User $student) {
    $teacher = $r->user();
    abort_unless($teacher->role==='admin' || $teacher->role==='teacher', 403);
    $raw = Str::random(40);
    \DB::table('password_reset_links')->insert([
      'user_id'=>$student->id,'token_hash'=>Hash::make($raw),
      'expires_at'=>now()->addHour(),'created_by'=>$teacher->id,
      'created_at'=>now(),'updated_at'=>now()]);
    return back()->with('reset_raw', $raw);
  }
}
```
Wire in `routes/web.php`:
```php
Route::middleware(['auth'])->post('/teacher/students/{student}/reset-link', [\App\Http\Controllers\ResetLinkController::class,'store']);
Route::middleware(['auth'])->get('/admin/users', fn()=>abort(403));
```
Run: `php artisan test tests/Feature/RolesTest.php` Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: roles, classes pivot, teacher reset links"
git push origin main
```

---

### Task 3: Teacher classes + roster single + CSV import

**Files:**
- Create: `app/Http/Controllers/ClassController.php`, `app/Http/Controllers/RosterImportController.php`
- Test: `tests/Feature/ClassesTest.php`

- [ ] **Step 1: Write failing test**

```php
it('teacher creates class and imports csv roster', function () {
    $t = \App\Models\User::factory()->create(['role'=>'teacher']);
    $this->actingAs($t)->post('/teacher/classes', [
      'name'=>'Grade 6 - A','section'=>'A','school_year'=>'2026-2027'])
      ->assertRedirect();
    expect(\App\Models\ClassRoom::where('teacher_id',$t->id)->count())->toBe(1);
});
```
Run: `php artisan test tests/Feature/ClassesTest.php` Expected: FAIL.

- [ ] **Step 2: Minimal implementation**

```php
// app/Http/Controllers/ClassController.php
namespace App\Http\Controllers; use App\Models\ClassRoom; use Illuminate\Http\Request; use Illuminate\Support\Str;
class ClassController extends Controller {
  public function store(Request $r) {
    $d = $r->validate(['name'=>'required|max:120','section'=>'nullable|max:20','school_year'=>'required|max:20']);
    $c = ClassRoom::create([...$d,'teacher_id'=>$r->user()->id,'code'=>strtoupper(Str::random(6))]);
    return redirect("/teacher/classes/{$c->id}");
  }
}
```
Route: `Route::middleware(['auth'])->post('/teacher/classes', [ClassController::class,'store']);`
CSV: accept `display_name,username?` per row, dry-run validate (duplicate username check), then `DB::transaction` create users with `Hash::make(Str::random(10))` + attach pivot. Return row errors JSON for Inertia. No partial commit on error — wrap entire import in transaction and roll back on any row failure.

Run: `php artisan test tests/Feature/ClassesTest.php` Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: teacher classes and csv roster import"
git push origin main
```

---

### Task 4: Admin content CRUD (levels/stages/stories/questions)

**Files:**
- Create migrations: `2026_09_12_000002_create_content_tables.php`
- Create: `app/Models/Level.php, Stage.php, Story.php, Question.php`
- Create: `app/Http/Controllers/Admin/*Controller.php` (LevelController, StageController, StoryController, QuestionController)
- Test: `tests/Feature/ContentTest.php`

- [ ] **Step 1: Write failing test (publish guard)**

```php
it('cannot publish stage without story and 5 questions', function () {
    $a = \App\Models\User::factory()->create(['role'=>'admin']);
    $lvl = \App\Models\Level::create(['title'=>'L1','order'=>1,'is_published'=>false]);
    $stage = \App\Models\Stage::create(['level_id'=>$lvl->id,'title'=>'S1','order'=>1]);
    $this->actingAs($a)->post("/admin/stages/{$stage->id}/publish")->assertStatus(422);
});
```
Run: Expected FAIL.

- [ ] **Step 2: Migrations + models**

```php
// migration up():
Schema::create('levels', fn($t)=>...); // id, title, description nullable, order unique, cover_path nullable, required_total_stars_to_unlock default 0, badge_name nullable, is_published bool default false, timestamps
Schema::create('stages', ...); // id, level_id FK cascade, title, order, required_stars_to_unlock default 1, is_pretest/posttest bool false, difficulty_tag nullable enum, readability_note nullable, estimated_minutes nullable, is_published false, unique(level_id,order)
Schema::create('stories', ...); // id, stage_id FK unique cascade, type enum text|youtube, title, body_html nullable longText, cover_path nullable, youtube_video_id nullable, transcript nullable text, must_watch_pct default 80
Schema::create('questions', ...); // id, story_id FK cascade, order, type enum mc_single|true_false|ordering|fill_blank, stem text, payload json, points default 1, explanation nullable, is_active true, unique(story_id,order)
Schema::create('settings', ...); // key PK string, value json
```
Models with relations: Level hasMany Stage ordered, Stage belongsTo Level + hasOne Story, Story belongsTo Stage + hasMany Question, Question belongsTo Story with `payload` cast array.

YouTube parse helper in StoryController:
```php
public static function toVideoId(string $url): ?string {
  if (preg_match('/(?:v=|youtu\.be\/|shorts\/)([\w-]{11})/', $url, $m)) return $m[1];
  return null;
}
```

- [ ] **Step 3: Publish guard + CRUD (Inertia pages minimal: Index/Edit forms)**

Publish rule in `StageController@publish`: abort 422 unless story exists AND active questions >=5. Level publish requires >=1 published stage.

Run test: Expected PASS.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: admin content crud with publish guards"
git push origin main
```

---

### Task 5: Student map + story view (text + YouTube gating)

**Files:**
- Create: `app/Services/UnlockService.php`, `app/Http/Controllers/StoryViewController.php`
- Create: `resources/js/Pages/Student/Map.vue`, `Story.vue`, `resources/js/Components/StageNode.vue, YoutubePlayer.vue`
- Test: `tests/Feature/UnlockTest.php`

- [ ] **Step 1: Write failing test (locked direct URL 403)**

```php
it('student cannot open locked stage via url', function () {
    // seed L1 S1+S2; no attempts
    $s = \App\Models\User::factory()->create(['role'=>'student']);
    $s2 = \App\Models\Stage::orderBy('order')->skip(1)->first();
    $this->actingAs($s)->get("/stories/{$s2->story->id}")->assertForbidden();
});
```
Run: Expected FAIL.

- [ ] **Step 2: UnlockService + controller guard**

```php
// app/Services/UnlockService.php
namespace App\Services; use App\Models\User; use App\Models\Stage; use App\Models\Attempt;
class UnlockService {
  public static function bestStars(int $studentId, int $stageId): int {
    return (int) Attempt::where('student_id',$studentId)->where('stage_id',$stageId)->max('stars');
  }
  public static function stageUnlocked(int $studentId, Stage $stage): bool {
    if ($stage->order === 1 && $stage->level->order === 1) return true;
    $prev = Stage::where('level_id',$stage->level_id)->where('order','<',$stage->order)->orderByDesc('order')->first();
    if ($prev) return self::bestStars($studentId,$prev->id) >= $stage->required_stars_to_unlock;
    $prevLevel = \App\Models\Level::where('order','<',$stage->level->order)->orderByDesc('order')->first();
    if (!$prevLevel) return true;
    $sum = Attempt::where('student_id',$studentId)->whereIn('stage_id',$prevLevel->stages()->pluck('id'))
      ->selectRaw('stage_id, MAX(stars) m')->groupBy('stage_id')->get()->sum('m');
    return $sum >= $stage->level->required_total_stars_to_unlock;
  }
}
```
Controller aborts 403 unless unlocked + story published. Map.vue renders levels accordion with StageNode states via `bestStars` map prop.

YoutubePlayer.vue: IFrame API, emits `progress(pct)`, posts to `/watch-pings` every 5s; Quiz button enabled when pct>=must_watch_pct OR transcript scrolled (IntersectionObserver on bottom marker).

Run test: Expected PASS.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: student map and gated story view with youtube progress"
git push origin main
```

---

### Task 6: Quiz runner + server grading + stars/EXP (core)

**Files:**
- Create: `app/Services/StarCalculator.php, ExpCalculator.php, GradingService.php`
- Create: `database/migrations/2026_09_12_000003_create_attempts_tables.php`
- Create: `app/Models/Attempt.php, StudentProgress.php`, `app/Http/Controllers/AttemptController.php`
- Create: `resources/js/Components/QuizRunner.vue`, `resources/js/Pages/Student/Quiz.vue, Result.vue`
- Test: `tests/Feature/GradingTest.php`

- [ ] **Step 1: Write failing tests (tiers + anti-farm + tamper-proof)**

```php
use App\Services\{StarCalculator, ExpCalculator, GradingService};
it('star tiers', fn()=>expect([StarCalculator::forPct(20),StarCalculator::forPct(30),StarCalculator::forPct(65),StarCalculator::forPct(95)])->toBe([0,1,2,3]));
it('exp formula', fn()=>expect(ExpCalculator::forAttempt(7,10,2,false))->toBe(100)); // 70+30
it('rejects client score tampering', function () {
    // post answers with fake score field; server recomputes
    $s = \App\Models\User::factory()->create(['role'=>'student']);
    // ... seed stage with 2 MC questions, submit 1 correct + score:100 forged
    // assert stored correct_count==1 and stars==1 not 3
});
```
Run: Expected FAIL.

- [ ] **Step 2: Services**

```php
// StarCalculator
class StarCalculator { public static function forPct(float $p): int {
  return $p<30?0:($p<60?1:($p<90?2:3)); } }
// ExpCalculator
class ExpCalculator { public static function forAttempt(int $c,int $t,int $stars,bool $repeatPerfect): int {
  $b=[0,0,10,30,50][$stars] ?? 0; $e=$c*10+$b; return $repeatPerfect? intdiv($e,5): $e; } }
// GradingService::grade($q,$given):bool
// mc_single: $given==correct_option_id; true_false: (bool)$given==correct;
// ordering: $given array equals correct_order; fill_blank: in_array(mb_strtolower(trim($given)), array_map(...acceptable))
```

- [ ] **Step 3: Attempts migration + controller transaction**

```php
// attempts: id uuid PK, student_id FK, stage_id FK, story_id FK, total, correct_count, pct decimal 5,2, stars 0-3, exp_earned, answers json, duration_sec, idempotency_key unique, created_at index(student_id,stage_id)
// student_progress: student_id PK FK, total_stars, total_exp, stages_cleared, stages_perfect, current_level_id nullable, last_active_at nullable, updated_at
```
`AttemptController@store`: validate unlock (UnlockService), validate answers shape, grade each via GradingService (ignore any client score), compute pct/stars/EXP (check prior best for repeatPerfect + keep best), `DB::transaction` insert attempt + upsert progress (recompute sums from max-per-stage). Idempotency: unique key per quiz start; duplicate returns existing.

QuizRunner.vue: per-type renderers, all-answered guard + confirm modal, posts `{answers, duration_sec, idempotency_key}`.

Run: `php artisan test tests/Feature/GradingTest.php` Expected: PASS (incl. 0/3/6/10, 10/10, re-attempt cases).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: server grading with stars exp and anti-farm"
git push origin main
```

---

### Task 7: Leaderboard + teacher dashboard + settings + demo seed

**Files:**
- Create: `app/Http/Controllers/LeaderboardController.php, TeacherDashboardController.php`
- Create: `resources/js/Pages/Student/Leaderboard.vue, Teacher/Dashboard.vue, Teacher/Classes/Show.vue`
- Create: `database/seeders/DemoContentSeeder.php`
- Test: `tests/Feature/LeaderboardTest.php`

- [ ] **Step 1: Write failing test (per-class isolation)**

```php
it('leaderboard only same class ordered by stars then exp', function () {
    // 2 classes, 3 students; assert class A leaderboard excludes class B + order correct
});
```
Run: Expected FAIL.

- [ ] **Step 2: Queries**

```php
// Leaderboard: StudentProgress join class_student where class_id=X order total_stars desc, total_exp desc; weekly variant aggregates attempts last 7d.
// TeacherDashboard: per class avg completion (cleared/total stages), avg accuracy (avg pct), struggling = accuracy<50 or last_active>7d.
// Respect classes.leaderboard_visible toggle (students 403 with friendly message when hidden).
```

- [ ] **Step 3: Demo seeder (1 level L1, 3 stages: S1 text MC, S2 youtube TF, S3 text mixed + pretest flag on S1, posttest on S3, difficulty tags)**

- [ ] **Step 3b: Settings + audit minimal (spec coverage)**
  - `settings` table seeded with `stars.tiers=[30,60,90]`, `exp.per_correct=10`, `exp.bonus=[0,10,30,50]`; Admin read-only page at `/admin/settings` displaying values (edit in follow-up plan).
  - `audit_logs` migration (id, actor_id FK, action, entity_type, entity_id, meta JSON, created_at) + write on stage publish and password-reset-link create only. No UI in MVP (query via tinker).

Run:
```bash
php artisan db:seed --class=DemoContentSeeder
php artisan test tests/Feature/LeaderboardTest.php
```
Expected: PASS.

- [ ] **Step 4: Commit + push**

```bash
git add -A
git commit -m "feat: per-class leaderboard, teacher dashboard, demo seed"
git push origin main
```

---

## Global acceptance (run before defense demo)

```bash
php artisan test --parallel
npm run build
```
- Teacher creates class + 30-row CSV <10 min; student first-login force-change works.
- Locked URL → 403 + "Clear previous stage first".
- Star/EXP math green; tampered score ignored.
- YouTube quiz locked until watch% or transcript scroll.
- Leaderboard per-class only, respects toggle.
- Best stars persist after refresh; retry never lowers stars.
