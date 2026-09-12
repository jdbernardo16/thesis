<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
            $t->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $t->unsignedInteger('total');
            $t->unsignedInteger('correct_count');
            $t->decimal('pct', 5, 2)->default(0);
            $t->unsignedTinyInteger('stars')->default(0);
            $t->unsignedInteger('exp_earned')->default(0);
            $t->json('answers')->nullable();
            $t->unsignedInteger('duration_sec')->nullable();
            $t->string('idempotency_key')->unique();
            $t->timestamps();
            $t->index(['student_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
