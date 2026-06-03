<?php

namespace App\Console\Commands;

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\ScheduledNotification;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * CheckScheduledNotifications
 * ──────────────────────────────────────────────────────────────
 * Runs EVERY MINUTE via the Laravel scheduler.
 * Fires browser push notifications for:
 *
 *  1. Per-habit reminders   — user sets "remind me at 8pm for Exercise"
 *  2. Streak warnings       — fires at 8pm if habit has a streak but isn't done today
 *  3. Task due reminders    — fires at user's chosen time if tasks are due today
 *  4. Daily summary         — end-of-day recap at user's chosen time
 *
 * REGISTRATION (routes/console.php):
 *
 *   Schedule::command('app:check-notifications')->everyMinute();
 *
 * SERVER CRON (add this to cPanel or VPS crontab):
 *
 *   * * * * * cd /path/to/olliesync && php artisan schedule:run >> /dev/null 2>&1
 *
 * REQUIRES: composer require minishlink/web-push
 * AND VAPID keys in .env (see SETUP_GUIDE.md)
 */
class CheckScheduledNotifications extends Command
{
    protected $signature   = 'app:check-notifications';
    protected $description = 'Fire any scheduled notifications that are due now';

    public function handle(): int
    {
        $now = Carbon::now();
        $this->info("[{$now->toDateTimeString()}] Checking notifications...");

        // ── 1. Fire individual scheduled notification rows ────
        $this->fireScheduledRows($now);

        // ── 2. Daily habit reminders (per-user global setting) ─
        $this->fireDailyHabitReminders($now);

        // ── 3. Task due today reminders ───────────────────────
        $this->fireTaskDueReminders($now);

        // ── 4. Streak warnings (fires after 8pm) ─────────────
        $this->fireStreakWarnings($now);

        // ── 5. Daily summary ──────────────────────────────────
        $this->fireDailySummary($now);

        $this->info("Done.");
        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────
    // 1. FIRE INDIVIDUAL SCHEDULED NOTIFICATION ROWS
    //    These are per-habit reminder rows created when the user
    //    sets an alarm via the bell icon on the habits page.
    // ─────────────────────────────────────────────────────────
    private function fireScheduledRows(Carbon $now): void
    {
        $due = ScheduledNotification::dueNow()
            ->with(['user.notificationPreference', 'habit', 'task'])
            ->get();

        $this->info("  → {$due->count()} scheduled rows due.");

        foreach ($due as $notification) {
            $prefs = $notification->user?->notificationPreference;

            // Check if the user has turned off this type of notification
            if ($notification->type === 'habit_reminder' && $prefs && !$prefs->notify_habit_reminder) {
                $notification->update(['fired_at' => $now]);
                continue;
            }

            // Send the push notification
            $sent = $this->sendPush(
                user:  $notification->user,
                title: $notification->title,
                body:  $notification->body ?? '',
                url:   '/habits',
                type:  $notification->type,
            );

            if ($sent) {
                $this->line("  ✓ Fired [{$notification->type}]: {$notification->title} → user #{$notification->user_id}");
            }

            // ── Handle repeat logic ────────────────────────────
            if ($notification->repeat_daily && $notification->repeat_time) {
                // Schedule for tomorrow at the same time
                $nextFire = Carbon::tomorrow()->setTimeFromTimeString($notification->repeat_time);
                $notification->update([
                    'fire_at'  => $nextFire,
                    'fired_at' => null,   // reset so it fires again tomorrow
                ]);
                $this->line("  ↻ Rescheduled for tomorrow at {$notification->repeat_time}");
            } else {
                // One-time notification — mark as fired
                $notification->update(['fired_at' => $now]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // 2. GLOBAL HABIT REMINDERS
    //    If a user has `notify_habit_reminder = true` and it's
    //    their global habit_reminder_time, send them a summary
    //    of how many habits they haven't done yet.
    // ─────────────────────────────────────────────────────────
    private function fireDailyHabitReminders(Carbon $now): void
    {
        // Find users whose habit_reminder_time matches the current minute
        $currentTime = $now->format('H:i');

        $prefs = UserNotificationPreference::where('notify_habit_reminder', true)
            ->where('is_active', true)  // global toggle
            ->get()
            ->filter(fn($p) => substr($p->habit_reminder_time, 0, 5) === $currentTime)
            ->values();

        foreach ($prefs as $pref) {
            if (!$pref->hasPushSubscription()) continue;

            $user = $pref->user;
            if (!$user) continue;

            // Count habits not done today
            $totalHabits = $user->habits()->where('is_archived', false)->count();
            $doneToday   = HabitCompletion::where('user_id', $user->id)
                ->where('completed_date', today())
                ->count();
            $remaining   = max(0, $totalHabits - $doneToday);

            if ($remaining > 0) {
                $this->sendPush(
                    user:  $user,
                    title: "Habit check! 🔁",
                    body:  "You have {$remaining} habit(s) left to do today. Keep going!",
                    url:   '/habits',
                    type:  'habit_reminder',
                );
                $this->line("  ✓ Global habit reminder → user #{$user->id} ({$remaining} remaining)");
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // 3. TASK DUE TODAY REMINDERS
    //    Fires at the user's chosen task_reminder_time (default 7:30am)
    // ─────────────────────────────────────────────────────────
    private function fireTaskDueReminders(Carbon $now): void
    {
        $currentTime = $now->format('H:i');

        $prefs = UserNotificationPreference::where('notify_task_due', true)
            ->get()
            ->filter(fn($p) => substr($p->task_reminder_time, 0, 5) === $currentTime)
            ->values();

        foreach ($prefs as $pref) {
            if (!$pref->hasPushSubscription()) continue;

            $user = $pref->user;
            if (!$user) continue;

            $dueToday = $user->tasks()
                ->where('completed', false)
                ->where('deadline', today()->toDateString())
                ->count();

            $overdue = $user->tasks()
                ->where('completed', false)
                ->whereNotNull('deadline')
                ->where('deadline', '<', today()->toDateString())
                ->count();

            if ($dueToday > 0 || $overdue > 0) {
                $parts = [];
                if ($dueToday) $parts[] = "{$dueToday} task(s) due today";
                if ($overdue)  $parts[] = "{$overdue} overdue";

                $this->sendPush(
                    user:  $user,
                    title: "Task reminder! 📋",
                    body:  implode(' · ', $parts) . ". Check your task list!",
                    url:   '/tasks',
                    type:  'task_due',
                );
                $this->line("  ✓ Task reminder → user #{$user->id}");
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // 4. STREAK WARNINGS
    //    Fires at 8:00 PM if a habit has an active streak
    //    but hasn't been completed today — so user can still save it.
    // ─────────────────────────────────────────────────────────
    private function fireStreakWarnings(Carbon $now): void
    {
        // Only fire around 8:00 PM
        if ($now->format('H:i') !== '20:00') return;

        $prefs = UserNotificationPreference::where('notify_streak_warning', true)
            ->with('user')
            ->get();

        foreach ($prefs as $pref) {
            if (!$pref->hasPushSubscription()) continue;
            $user = $pref->user;
            if (!$user) continue;

            // Find habits that have a streak but haven't been done today
            $atRisk = Habit::where('user_id', $user->id)
                ->where('is_archived', false)
                ->where('current_streak', '>', 0)
                ->whereDoesntHave('completions', fn($q) =>
                    $q->where('completed_date', today()->toDateString())
                )
                ->get();

            foreach ($atRisk as $habit) {
                $this->sendPush(
                    user:  $user,
                    title: "Streak at risk! 🔥",
                    body:  "\"{$habit->title}\" — {$habit->current_streak} day streak. Complete it before midnight!",
                    url:   '/habits',
                    type:  'streak_warning',
                );
                $this->line("  ✓ Streak warning for \"{$habit->title}\" → user #{$user->id}");
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // 5. DAILY SUMMARY
    //    Sends a recap at the user's chosen summary time (default 9pm).
    // ─────────────────────────────────────────────────────────
    private function fireDailySummary(Carbon $now): void
    {
        $currentTime = $now->format('H:i');

        $prefs = UserNotificationPreference::where('notify_daily_summary', true)
            ->get()
            ->filter(fn($p) => substr($p->daily_summary_time, 0, 5) === $currentTime)
            ->values();

        foreach ($prefs as $pref) {
            if (!$pref->hasPushSubscription()) continue;
            $user = $pref->user;
            if (!$user) continue;

            // Count today's activity
            $tasksDone  = $user->tasks()->where('completed', true)->whereDate('completed_at', today())->count();
            $habitsDone = HabitCompletion::where('user_id', $user->id)->where('completed_date', today())->count();
            $focusToday = $user->timerSessions()
                ->where('status', 'completed')->where('mode', 'focus')
                ->whereDate('session_date', today())
                ->sum('actual_duration');
            $focusMin   = (int) round($focusToday / 60);

            $this->sendPush(
                user:  $user,
                title: "Today's recap 📊",
                body:  "✅ {$tasksDone} tasks · 🔁 {$habitsDone} habits · ⏱ {$focusMin} min focused. Great day!",
                url:   '/analytics',
                type:  'daily_summary',
            );
            $this->line("  ✓ Daily summary → user #{$user->id}");
        }
    }

    // ─────────────────────────────────────────────────────────
    // SEND WEB PUSH
    //
    // Uses minishlink/web-push under the hood.
    // Returns true if sent, false if not (e.g. no subscription).
    //
    // SETUP:
    //   composer require minishlink/web-push
    //   Then in .env:
    //     VAPID_PUBLIC_KEY=xxx
    //     VAPID_PRIVATE_KEY=xxx
    //     VAPID_SUBJECT=mailto:you@youremail.com
    // ─────────────────────────────────────────────────────────
    private function sendPush(User $user, string $title, string $body, string $url = '/', string $type = 'general'): bool
    {
        $prefs = $user->notificationPreference;

        if (!$prefs || !$prefs->hasPushSubscription()) {
            $this->line("  ⚠ No push subscription for user #{$user->id}");
            return false;
        }

        $subscriptionData = $prefs->getPushSubscriptionArray();
        if (!$subscriptionData) return false;

        // Check if web-push is installed
        if (!class_exists(\Minishlink\WebPush\WebPush::class)) {
            Log::warning("Web Push not installed. Run: composer require minishlink/web-push");
            $this->warn("  Web Push not installed. Run: composer require minishlink/web-push");
            return false;
        }

        try {
            $webPush = new \Minishlink\WebPush\WebPush([
                'VAPID' => [
                    'subject'    => config('services.vapid.subject'),
                    'publicKey'  => config('services.vapid.public_key'),
                    'privateKey' => config('services.vapid.private_key'),
                ]
            ]);

            $subscription = \Minishlink\WebPush\Subscription::create($subscriptionData);

            $payload = json_encode([
                'title' => $title,
                'body'  => $body,
                'icon'  => '/favicon.ico',
                'badge' => '/favicon.ico',
                'url'   => $url,
                'type'  => $type,
            ]);

            $webPush->queueNotification($subscription, $payload);

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    return true;
                } else {
                    Log::warning("Push failed for user #{$user->id}: " . $report->getReason());
                    // If subscription expired, remove it
                    if ($report->isSubscriptionExpired()) {
                        $prefs->update(['push_subscription' => null]);
                        $this->warn("  Push subscription expired for user #{$user->id} — removed.");
                    }
                    return false;
                }
            }
        } catch (\Exception $e) {
            Log::error("Push error for user #{$user->id}: " . $e->getMessage());
            $this->error("  Push error: " . $e->getMessage());
            return false;
        }

        return true;
    }
}