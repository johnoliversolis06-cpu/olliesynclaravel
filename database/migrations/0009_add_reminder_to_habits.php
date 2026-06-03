<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FILE PATH: database/migrations/0009_add_reminder_to_habits.php
 *
 * REPLACE your entire 0009 migration file with this.
 * Uses hasColumn() and hasTable() checks so it never crashes
 * even if some parts already exist from a previous migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add reminder_time to habits (safe — skips if already exists) ──
        if (!Schema::hasColumn('habits', 'reminder_time')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->time('reminder_time')->nullable()->after('color');
            });
        }

        // ── 2. Create user_notification_preferences (safe — skips if exists) ─
        if (!Schema::hasTable('user_notification_preferences')) {
            Schema::create('user_notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->boolean('notify_task_due')->default(true);
                $table->boolean('notify_habit_reminder')->default(true);
                $table->boolean('notify_focus_complete')->default(true);
                $table->boolean('notify_daily_summary')->default(false);
                $table->boolean('notify_streak_warning')->default(true);
                $table->time('habit_reminder_time')->default('08:00');
                $table->time('task_reminder_time')->default('07:30');
                $table->time('daily_summary_time')->default('21:00');
                $table->text('push_subscription')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique('user_id');
            });
        }

        // ── 3. Create scheduled_notifications (safe — skips if exists) ────────
        if (!Schema::hasTable('scheduled_notifications')) {
            Schema::create('scheduled_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('habit_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('task_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('type')->default('habit_reminder');
                $table->dateTime('fire_at');
                $table->boolean('repeat_daily')->default(false);
                $table->time('repeat_time')->nullable();
                $table->dateTime('fired_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['user_id', 'fire_at', 'fired_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_notifications');
        Schema::dropIfExists('user_notification_preferences');

        if (Schema::hasColumn('habits', 'reminder_time')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->dropColumn('reminder_time');
            });
        }
    }
};