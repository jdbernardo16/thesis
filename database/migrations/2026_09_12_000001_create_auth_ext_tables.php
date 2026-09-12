<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('student')->after('password');
            $t->string('username')->unique()->nullable()->after('email');
            $t->boolean('must_change_password')->default(false);
            $t->string('avatar_color')->default('#4F46E5');
            $t->boolean('active')->default(true);
        });

        Schema::create('password_reset_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash')->unique();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('classes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $t->string('name');
            $t->string('section')->nullable();
            $t->string('school_year');
            $t->string('code')->unique();
            $t->boolean('leaderboard_visible')->default(true);
            $t->timestamps();
        });

        Schema::create('class_student', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $t->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('joined_at')->useCurrent();
            $t->unique(['class_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_student');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('password_reset_links');

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['role', 'username', 'must_change_password', 'avatar_color', 'active']);
        });
    }
};
