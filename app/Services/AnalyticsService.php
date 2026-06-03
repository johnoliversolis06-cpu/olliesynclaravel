<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\Task;
use App\Models\TimerSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AnalyticsService
 * FILE PATH: app/Services/AnalyticsService.php
 *
 * REPLACE your entire existing AnalyticsService.php with this file.
 * This adds the missing getWeeklyFocus() method + keeps everything else.
 */
class AnalyticsService
{
    // ─────────────────────────────────────────────────────────
    // DASHBOARD STATS (already worked — kept as-is)
    // ─────────────────────────────────────────────────────────
    public function getDashboardStats(User $user): array
    {
        $today = today()->toDateString();

        $totalFocusToday = TimerSession::where('user_id', $user->id)
            ->where('session_date', $today)
            ->where('mode', 'focus')
            ->where('status', 'completed')
            ->sum('actual_duration');

        $nextCount = TimerSession::where('user_id', $user->id)
            ->where('session_date', $today)
            ->where('mode', 'focus')
            ->where('status', 'completed')
            ->count();

        $tasksTodo = Task::where('user_id', $user->id)
            ->where('completed', false)
            ->count();

        $tasksCompletedToday = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->whereDate('completed_at', $today)
            ->count();

        $habitsTotal = Habit::where('user_id', $user->id)
            ->where('is_archived', false)
            ->count();

        $habitsCompletedToday = HabitCompletion::where('user_id', $user->id)
            ->where('completed_date', $today)
            ->count();

        $pomodorosToday = TimerSession::where('user_id', $user->id)
            ->where('session_type', 'pomodoro')
            ->where('mode', 'focus')
            ->where('status', 'completed')
            ->whereDate('session_date', $today)
            ->count();

        return [
            'total_focus_seconds_today' => (int) $totalFocusToday,
            'next_count'                => (int) $nextCount,
            'tasks_todo'                => (int) $tasksTodo,
            'tasks_completed_today'     => (int) $tasksCompletedToday,
            'habits_total'              => (int) $habitsTotal,
            'habits_completed_today'    => (int) $habitsCompletedToday,
            'pomodoros_today'           => (int) $pomodorosToday,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // ✅ FIX: THIS WAS THE MISSING METHOD
    // Weekly focus data — last 7 days
    // Returns: [{ day: 'Mon', date: '2025-05-25', seconds: 3600 }, ...]
    // ─────────────────────────────────────────────────────────
    public function getWeeklyFocus(User $user): array
    {
        $days = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            $seconds = TimerSession::where('user_id', $user->id)
                ->where('session_date', $date->toDateString())
                ->where('mode', 'focus')
                ->where('status', 'completed')
                ->sum('actual_duration');

            $days[] = [
                'day'     => $date->format('D'),          // Mon, Tue, Wed...
                'date'    => $date->toDateString(),        // 2025-05-25
                'seconds' => (int) $seconds,
            ];
        }

        return $days;
    }

    // ─────────────────────────────────────────────────────────
    // CALCULATE CURRENT STREAK FOR A HABIT
    // (already worked — kept as-is)
    // ─────────────────────────────────────────────────────────
    public function calculateCurrentStreak(Habit $habit): int
    {
        // Use the stored column if it's up to date
        if ($habit->current_streak !== null) {
            return (int) $habit->current_streak;
        }

        // Otherwise compute it from completions
        $streak  = 0;
        $checkDate = Carbon::today();

        while (true) {
            $done = HabitCompletion::where('habit_id', $habit->id)
                ->where('completed_date', $checkDate->toDateString())
                ->exists();

            if (!$done) break;

            $streak++;
            $checkDate->subDay();
        }

        return $streak;
    }

    // ─────────────────────────────────────────────────────────
    // RECALCULATE AND SAVE STREAKS FOR ALL HABITS OF A USER
    // Called by: php artisan app:recalculate-streaks
    // ─────────────────────────────────────────────────────────
    public function recalculateStreaksForUser(User $user): void
    {
        $habits = Habit::where('user_id', $user->id)
            ->where('is_archived', false)
            ->get();

        foreach ($habits as $habit) {
            $streak     = 0;
            $checkDate  = Carbon::today();
            $longest    = $habit->longest_streak ?? 0;

            while (true) {
                $done = HabitCompletion::where('habit_id', $habit->id)
                    ->where('completed_date', $checkDate->toDateString())
                    ->exists();

                if (!$done) break;
                $streak++;
                $checkDate->subDay();
            }

            $longest = max($longest, $streak);

            $habit->update([
                'current_streak' => $streak,
                'longest_streak' => $longest,
            ]);
        }
    }
}