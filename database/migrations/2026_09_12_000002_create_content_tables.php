<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->unsignedInteger('order')->unique();
            $t->string('cover_path')->nullable();
            $t->unsignedInteger('required_total_stars_to_unlock')->default(0);
            $t->string('badge_name')->nullable();
            $t->boolean('is_published')->default(false);
            $t->timestamps();
        });

        Schema::create('stages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $t->string('title');
            $t->unsignedInteger('order');
            $t->unsignedInteger('required_stars_to_unlock')->default(1);
            $t->boolean('is_pretest')->default(false);
            $t->boolean('is_posttest')->default(false);
            $t->string('difficulty_tag')->nullable();
            $t->text('readability_note')->nullable();
            $t->unsignedInteger('estimated_minutes')->nullable();
            $t->boolean('is_published')->default(false);
            $t->timestamps();
            $t->unique(['level_id', 'order']);
        });

        Schema::create('stories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stage_id')->unique()->constrained('stages')->cascadeOnDelete();
            $t->string('type');
            $t->string('title');
            $t->longText('body_html')->nullable();
            $t->string('cover_path')->nullable();
            $t->string('youtube_video_id', 11)->nullable();
            $t->text('transcript')->nullable();
            $t->unsignedTinyInteger('must_watch_pct')->default(80);
            $t->timestamps();
        });

        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $t->unsignedInteger('order');
            $t->string('type');
            $t->text('stem');
            $t->json('payload');
            $t->unsignedInteger('points')->default(1);
            $t->text('explanation')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['story_id', 'order']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('stages');
        Schema::dropIfExists('levels');
    }
};
