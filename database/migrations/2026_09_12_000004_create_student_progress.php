<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_progress', function (Blueprint $t) {
            $t->foreignId('student_id')->primary()->constrained('users')->cascadeOnDelete();
            $t->unsignedInteger('total_stars')->default(0);
            $t->unsignedInteger('total_exp')->default(0);
            $t->unsignedInteger('stages_cleared')->default(0);
            $t->unsignedInteger('stages_perfect')->default(0);
            $t->foreignId('current_level_id')->nullable()->constrained('levels')->nullOnDelete();
            $t->timestamp('last_active_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_progress');
    }
};
