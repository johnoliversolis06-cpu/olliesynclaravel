<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Scheduled Notifications + User Notification Preferences
 *
 * This adds:
 *  1. user_notification_preferences  — per-user settings for what notifications to receive
 *  2. scheduled_notifications        — alarm/reminder rows that the queue worker will fire
 *
 * HOW SCHEDULED NOTIFICATIONS WORK IN LARAVEL
 * ─────────────────────────────────────────────
 * 1. User sets an alarm via the Settings page  (e.g. "Remind me to journal at 9pm")
 * 2. A row is inserted into `scheduled_notifications`
 * 3. A Laravel console command  (CheckScheduledNotifications)  runs every minute:
 *    `php artisan app:check-notifications`
 *    and is registered in routes/console.php or Kernel.php as:
 *    Schedule::command('app:check-notifications')->everyMinute();
 * 4. That command finds rows where `fire_at <= now()` and `fired_at IS NULL`,
 *    sends a browser push (or email), and marks them as fired.
 * 5. For FREE browser push:  use the Web Push API with VAPID keys.
 *    Package: `composer require minishlink/web-push`  (free, open-source)
 *
 * CRON SETUP (server)
 * ───────────────────
 * Add this to your server crontab (cPanel > Cron Jobs or VPS):
 *   * * * * * cd /path/to/olliesync && php artisan schedule:run >> /dev/null 2>&1
 */

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. User notification preferences ─────────────────────
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Which notifications does the user want?
            $table->boolean('notify_task_due')->default(true);        // "You have tasks due today"
            $table->boolean('notify_habit_reminder')->default(true);  // "Don't forget your habits"
            $table->boolean('notify_focus_complete')->default(true);  // "Focus session finished"
            $table->boolean('notify_daily_summary')->default(false);  // "Here's your day recap"
            $table->boolean('notify_streak_warning')->default(true);  // "Your streak is at risk!"

            // What time to send daily reminders (24hr format, e.g. "09:00")
            $table->time('habit_reminder_time')->default('08:00');
            $table->time('task_reminder_time')->default('07:30');
            $table->time('daily_summary_time')->default('21:00');

            // Web Push subscription payload (for browser notifications)
            // Stored as JSON: { endpoint, keys: { p256dh, auth } }
            $table->text('push_subscription')->nullable();

            $table->timestamps();
            $table->unique('user_id');
        });

        // ── 2. Scheduled notifications (alarm rows) ───────────────
        Schema::create('scheduled_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('title');                          // Notification title
            $table->text('body')->nullable();                 // Notification body
            $table->string('type')->default('reminder');      // reminder | habit | task | focus | streak

            // What it refers to (optional)
            $table->foreignId('task_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('habit_id')->nullable()->constrained()->onDelete('cascade');

            // When to fire
            $table->dateTime('fire_at');                      // Exact datetime to send
            $table->boolean('repeat_daily')->default(false);  // Does it repeat every day?
            $table->time('repeat_time')->nullable();          // If repeating, at what time?

            // Tracking
            $table->dateTime('fired_at')->nullable();         // When it was actually sent
            $table->boolean('is_active')->default(true);      // Can be disabled without deleting

            $table->timestamps();
            $table->index(['user_id', 'fire_at', 'fired_at']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_notifications');
        Schema::dropIfExists('user_notification_preferences');
    }
};