<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('timer_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('task_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('habit_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('session_type', ['pomodoro', 'open'])->default('pomodoro');
            $table->enum('mode', ['focus', 'break', 'long_break'])->default('focus');
            $table->integer('planned_duration')->default(0);
            $table->integer('actual_duration')->default(0);
            $table->integer('paused_duration')->default(0);
            $table->integer('pomodoro_count')->default(0);
            $table->integer('interruption_count')->default(0);
            $table->enum('status', ['active', 'paused', 'completed', 'abandoned'])->default('active');
            $table->timestamp('started_at')->useCurrent(); // Quick fix for 'started_at cannot be null' edge cases
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->date('session_date');
            $table->timestamps();

            $table->index(['user_id', 'session_date']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'session_type', 'session_date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('timer_sessions');
    }
};