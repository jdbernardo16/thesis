# Bridging the Reading Gap: Interactive Assistive Program for Grade 6 Pupils — Design Spec

**Date:** 2026-09-12
**Status:** Approved design (Approach A — Thesis MVP + light research slice)
**Stack:** Laravel 11 + Inertia + Vue 3 + Tailwind + MySQL
**Thesis:** "Bridging the Reading Gap: Development of an Interactive Assistive Program for Grade 6 Pupils" (Bulacan State University)

## 1. Overview & Purpose

A gamified web app to improve Grade 6 reading comprehension. Pupils read a story (text) or watch a story (YouTube embed), then answer comprehension questions. Performance earns stars and EXP, unlocks further stages, and feeds a per-class leaderboard and teacher progress dashboard.

Primary users are Grade 6 pupils (ages ~11-12, school PCs/tablets, possibly low bandwidth). Secondary users are class teachers facilitating intervention. Tertiary is admin (researchers/IT) authoring content.

Success for thesis pilot: pupils can complete a Level end-to-end unassisted after one orientation; teachers can create a class, enroll students, view progress, and reset passwords without developer help; stars/EXP/leaderboard update correctly; pre/post-test flagged stories produce measurable score deltas.

## 2. Goals / Non-Goals

**Goals (MVP):**
- Role-based access: Admin, Teacher, Student.
- Teacher-managed classes with many students; admin creates teacher accounts.
- Level > Stage (= 1 Story + Quiz) progression with star gating and EXP.
- Story types: text (+ cover image) OR YouTube video (+ transcript).
- Mixed auto-gradable question types.
- Per-class leaderboard + teacher progress view.
- Admin CRUD for levels, stages, stories, questions, users.
- Thesis-measurable: difficulty tags + pretest/posttest flags.

**Non-Goals (Phase 2):**
- No global/school-wide leaderboard (per-class only for privacy).
- No combined text+video in one story (one type per story for MVP).
- No avatars shop, badges shop, streaks, audio narration, offline PWA, mobile native.
- No student self-registration or email-based reset.
- No AI-generated stories/questions.

## 3. Roles & Auth

### 3.1 Roles
- **Admin:** full access. Creates/edits teachers, all levels/stages/stories/questions, all classes (view), system settings (star thresholds, EXP constants, YouTube domain allowlist), CSV import/export, resets any password.
- **Teacher:** owns classes. Creates class, creates student accounts manually + CSV import (username, display name, temp password), views own classes only, views student progress breakdown, generates student password-reset links, toggles leaderboard visibility per class, cannot create other teachers or edit content library (read-only preview allowed).
- **Student:** plays assigned classes only. Views game map, reads/watches story, submits quiz, views own stars/EXP/progress + class leaderboard (if enabled). Cannot see other classes, cannot edit content, cannot change username.

### 3.2 Auth & Account Lifecycle
- Laravel session auth via Inertia (Breeze-style). `users` table has `role: admin|teacher|student`, `username` unique (e.g. `g6-2026-001`), `display_name`, `password_hash`, `must_change_password` bool.
- Admin creates teacher: name, username, temp password → teacher forced to change on first login.
- Teacher creates student: within class context; system generates username if blank (`<class-code>-<seq>`), temp password (8 chars). `must_change_password=true` shows change screen on first login (no email needed).
- Password reset (students): teacher clicks Reset → generates signed single-use token link valid 60 min (e.g. `/reset/:token`), gives/shows to pupil; pupil sets new password. No email. Admin can do same for teachers.
- Authorization via Policies + `role` middleware: `TeacherOwnsClass`, `StudentInClass`, `CanPlayStage (unlocked check server-side)`.
- Session timeout 120 min; school shared-device safe: explicit Logout prominent; remember-me disabled for students.

## 4. Content Model — Level > Stage > Story > Questions

Hierarchy is strict and linear within a Level for MVP (no branching).

**Level:** id, title (e.g. "Level 1: Pagsisimula"), description, order (int unique), cover_image, `required_total_stars_to_unlock` (int, for next level gate; Level 1 = 0), `badge_name`, is_published bool. Example: L1 has 5 stages, max 15 stars, requires 8 stars to open L2.

**Stage:** id, level_id, order within level, title, `required_stars_to_unlock` (default 1 — means previous stage must have ≥1 star; admin can set higher), story_id (one-to-one for MVP), `is_pretest` bool, `is_posttest` bool, `difficulty_tag` enum (`frustration|instructional|independent`), `readability_note` (free text, e.g. Phil-IRI level), estimated_minutes, is_published bool.

**Story:** id, stage_id, type enum (`text|youtube`), title, cover_image nullable, body_richtext (for text; sanitized HTML, supports headings, images), `youtube_url` (for youtube; must match allowlist `youtube.com|youtu.be`, stored as video_id), transcript/summary (required for youtube — ensures reading still present + fallback if video blocked), `must_watch_pct` default 80.

**Question:** id, story_id, order, type enum (`mc_single|true_false|ordering|fill_blank`), stem (text), payload JSON (validated per type), points default 1, explanation (shown on result review), is_active bool.
- `mc_single`: options[4] {id, text}, correct_option_id.
- `true_false`: correct boolean.
- `ordering`: items[4-5] shuffled to pupil, correct_order ids.
- `fill_blank`: acceptable_answers[] (case-insensitive trim match, 1-3 variants).
- 5–10 questions per story for MVP. All auto-gradable, no manual grading.

Admin authoring UX: CRUD wizards — Level list → Stage list → Story editor (type switcher with live preview: text renderer / YouTube embed validator showing thumbnail + duration) → Question builder (type switcher with per-type form + validation). Publish toggles cascade-check: cannot publish stage if story missing or <5 active questions; cannot publish level if 0 published stages.

## 5. Game Rules — Stars, EXP, Unlocking, Progress

### 5.1 Scoring & Stars (proportional tiers)
For an attempt: `pct = correct_count / total_questions * 100`.
- 0–29% → 0 stars (Fail, must retry, stage stays Current, next stays Locked).
- 30–59% → 1 star (Clear).
- 60–89% → 2 stars.
- 90–100% → 3 stars.
Thresholds are system settings editable by admin (defaults above) but fixed per deployment for thesis consistency. Display stars with animation on result screen. Best stars per (student, stage) retained; stars never decrease.

### 5.2 Unlocking
- Stage N+1 unlocks iff Stage N best_stars ≥ Stage N+1.required_stars_to_unlock (default 1).
- Level L+1 unlocks iff sum(best_stars in Level L) ≥ Level L+1 unlock requirement (via Level.required_total_stars_to_unlock on the target level; e.g. L2.requires 8 stars from L1).
- Backend enforces on every story-view and attempt-submit: 403 if locked (no client-only gating).
- Map states: Locked (grey + lock), Current (highlighted), Cleared 1–3 stars (stars shown), Perfect (gold).

### 5.3 EXP & Progress
- EXP formula: `exp = correct_count*10 + star_bonus` where bonus 0★=0, 1★=10, 2★=30, 3★=50. Example: 7/10 with 2★ = 70+30=100 EXP.
- Anti-farming: if re-attempting an already-3★ stage, EXP = 20% rounded down; if beating previous best stars, full EXP for that attempt; otherwise 50% EXP. Best stars kept, EXP always accumulates (capped per day? No cap for MVP — log all).
- StudentProgress (derived + cached): total_stars (sum best), total_exp (sum), stages_cleared, stages_perfect, current_level_id, last_active_at. Recomputed on attempt submit via transaction.
- Thesis metric: pretest/posttest flagged stages excluded from unlock gating? Decision: they follow same gating but are marked for reporting so teacher can compare pre vs post pct per pupil.

### 5.4 Quiz UX Rules
- Text story: paginated, font-size control (S/M/L), estimated time shown.
- YouTube story: embed via IFrame API; Answer button disabled until watch_pct ≥ must_watch_pct OR transcript scrolled to bottom (either satisfies, to handle blocked video). Watch progress posted periodically.
- Quiz: one question per screen, progress bar (Q3/8), Next/Back allowed before final Submit, Submit requires all answered (confirm modal if incomplete). Timer optional display only (duration_sec logged for research, no timeout fail for MVP).
- Result: score, correct/total, stars earned (animated), EXP gained, per-question review with explanation, buttons Retry / Next Stage (if unlocked) / Map.

## 6. Screens & Flows

### 6.1 Student
Login → My Classes (usually 1) → Game Map (levels accordion, stages as path with star states) → Story View → Quiz → Result → Map updates + Leaderboard toast if rank changed. Persistent header: total stars, total EXP, avatar initial, class name, logout. Extra tabs: Leaderboard (per-class, toggles All-time / This Week, shows rank, name, stars, EXP; teacher can hide names to initials), My Progress (per-level stars bar, per-stage table, accuracy %, time spent).

### 6.2 Teacher
Login → Dashboard (cards: my classes count, total students, avg completion %, avg accuracy %, struggling list: students with <50% avg or 0 activity 7 days). Class Detail: roster table (name, username, stars, EXP, cleared/total, last active, actions: view detail, reset password, remove), per-stage matrix (rows students × columns stages with star cells), tabs for Leaderboard preview + Settings (leaderboard visible bool, allow retry bool default true). Create Class: name, school year, section → generates class code (e.g. `G6-A-2026`). Enroll: Add single + CSV import template (columns: display_name, username optional) with dry-run error report. Student Detail: attempt history (date, stage, score, stars, EXP, duration), accuracy trend.

### 6.3 Admin
Dashboard (counts: teachers, students, levels, stories, attempts today). Content: Levels → Stages → Stories → Questions nested CRUD with publish validation + preview as student. Users: teachers table (create/disable/reset), students search across classes (view + reset, no edit of class ownership). Classes: read-only list + reassign teacher. System: star thresholds, EXP constants, YouTube allowlist, leaderboard defaults. Imports/Exports: CSV templates + logs.

## 7. Architecture

Laravel 11 monolith + Inertia.js + Vue 3 + Tailwind. MySQL 8 (or Postgres — one, default MySQL for school hosting). Filesystem: local `storage/app/public` for MVP (covers), with S3 driver ready via env. No separate API; Inertia pages + a small JSON endpoint for YouTube watch progress pings.

**Inertia pages (Vue SFCs):** Auth/Login, ChangePassword, Admin/Dashboard, Admin/Levels/*, Admin/Stages/*, Admin/Stories/Edit, Admin/Questions/Builder, Admin/Users, Teacher/Dashboard, Teacher/Classes/Show (+ Roster, Matrix), Student/Map, Student/Story, Student/Quiz, Student/Result, Student/Leaderboard, Student/Progress, Shared components: StarRating, ExpBadge, StageNode, QuizRunner (per-type renderers), YoutubePlayer wrapper.

**Server actions (Controllers):** AttemptController@store (validates unlock, grades server-side from canonical answers — never trust client score, computes stars/EXP in DB transaction, updates progress), WatchProgressController@ping, PasswordResetLinkController (signed tokens), CsvImportController (queued job for >100 rows).

**Security:** role middleware, policies, server-side grading + gating, signed reset tokens, rate limit attempts (10/min/IP+user), XSS sanitize story HTML (Purifier), YouTube URL strict parse to video_id, CSRF via Inertia, audit log for admin publishes + password resets.

**Error handling:** locked stage → 403 + friendly "Clear previous stage first" redirect to map; validation errors inline; quiz submit idempotency key (UUID per attempt start) prevents double-submit double-EXP; YouTube API failure → allow transcript-scroll path; CSV import → row-level error report, no partial commit (transaction or staged table).

## 8. Data Schema (MVP tables)

- users(id, role, name/display_name, username unique, password, avatar_color, must_change_password, active, timestamps)
- classes(id, teacher_id FK, name, section, school_year, code unique, leaderboard_visible bool default true, timestamps)
- class_student(id, class_id, student_id, joined_at, unique(class_id, student_id))
- levels(id, title, description, order unique, cover_path nullable, required_total_stars_to_unlock int default 0, badge_name nullable, is_published bool, timestamps)
- stages(id, level_id FK, title, order, required_stars_to_unlock default 1, story_id nullable unique, is_pretest bool, is_posttest bool, difficulty_tag enum nullable, readability_note nullable, estimated_minutes nullable, is_published bool, unique(level_id, order))
- stories(id, stage_id FK unique, type enum, title, body_html nullable, cover_path nullable, youtube_video_id nullable, transcript nullable, must_watch_pct default 80, timestamps)
- questions(id, story_id FK, order, type enum, stem, payload JSON, points default 1, explanation nullable, is_active bool, unique(story_id, order))
- attempts(id UUID, student_id FK, stage_id FK, story_id FK, total int, correct_count int, pct decimal, stars 0-3, exp_earned int, answers JSON (question_id → given), duration_sec int, is_best bool default false, created_at; index(student_id, stage_id, created_at))
- student_progress(student_id PK FK, total_stars int, total_exp int, stages_cleared int, stages_perfect int, current_level_id nullable, last_active_at nullable, updated_at)
- password_reset_links(id, user_id FK, token_hash, expires_at, used_at nullable, created_by FK)
- audit_logs(id, actor_id FK, action, entity_type, entity_id, meta JSON, created_at)
- settings(key PK, value JSON) — star thresholds, exp constants.

Derived leaderboard: query student_progress join class_student where class_id=X order by total_stars desc, total_exp desc. Weekly variant filters attempts in last 7d aggregated (no extra table for MVP; add index).

## 9. Validation & Acceptance

- Teacher can create class + add 30 students via CSV in <10 min; student first-login force-change works without email.
- Pupil cannot open locked stage via direct URL (403 tested).
- Star tiers + EXP math covered by feature tests (0/3/6/10, 10/10 cases + re-attempt farming rule).
- All question types grade correctly server-side; tampered client score ignored.
- YouTube story blocks quiz until watch threshold or transcript scroll.
- Leaderboard shows only same-class pupils, ordered correctly, respects visibility toggle.
- Map persists best stars after refresh; retry never reduces stars.
- Pest/PHPUnit: roles/policies, gating, grading, EXP, CSV import; Cypress/Dusk happy path: login → story → quiz → result → unlock next.

## 10. MVP Build Order (for implementation plan)

1. Auth + roles + change-password + reset-link. 2. Classes + roster + CSV. 3. Levels/Stages/Stories/Questions admin CRUD + publish guards. 4. Student map + story view (text first, YouTube second). 5. Quiz runner + server grading + stars/EXP/progress transaction. 6. Leaderboard + teacher dashboard/matrix. 7. Settings + audit + polish + seed demo content (1 level, 3 stages, text + YouTube + mixed Qs).

## 11. Decisions Log

- Technical specs only (no thesis chapters in this doc).
- Level > Stage (=Story) linear; one story per stage.
- Proportional stars (30/60/90), ≥1★ unlocks next, level needs accumulated stars.
- Mixed Q types, all auto-gradable.
- Admin→teacher→student provisioning; teacher-issued reset links (no email).
- Per-class leaderboard only.
- EXP from stars (10/correct + bonus, anti-farm factor).
- Story = text OR YouTube per story.
- Inertia monolith (not decoupled SPA).
