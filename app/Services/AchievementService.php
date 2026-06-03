<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\Task;
use App\Models\TimerSession;
use App\Models\User;
use App\Models\UserAchievement;
use Carbon\Carbon;

/**
 * AchievementService
 * FILE PATH: app/Services/AchievementService.php
 *
 * Call AchievementService::checkAll($user) after any major action
 * (completing a habit, task, or focus session).
 *
 * Usage in HabitController::complete():
 *   app(AchievementService::class)->checkAll(auth()->user());
 */
class AchievementService
{
    // ─────────────────────────────────────────────────────────
    // ALL ACHIEVEMENT DEFINITIONS
    // These are seeded once into the achievements table.
    // ─────────────────────────────────────────────────────────
    public static function definitions(): array
    {
        return [
            // ── Habits ──────────────────────────────────────
            ['key' => 'first_habit_done',  'icon' => '🌱', 'tier' => 'bronze',   'points' => 10,  'category' => 'habits', 'title' => 'First Step',        'description' => 'Complete your first habit'],
            ['key' => 'habit_streak_3',    'icon' => '🔥', 'tier' => 'bronze',   'points' => 15,  'category' => 'habits', 'title' => '3-Day Streak',       'description' => 'Keep a habit going for 3 days in a row'],
            ['key' => 'habit_streak_7',    'icon' => '🔥', 'tier' => 'silver',   'points' => 30,  'category' => 'habits', 'title' => 'One Week Strong',    'description' => '7-day habit streak'],
            ['key' => 'habit_streak_21',   'icon' => '🔥', 'tier' => 'gold',     'points' => 75,  'category' => 'habits', 'title' => '3-Week Warrior',     'description' => '21-day streak — habits are forming!'],
            ['key' => 'habit_streak_66',   'icon' => '💎', 'tier' => 'platinum', 'points' => 200, 'category' => 'habits', 'title' => 'Automatic',          'description' => '66 days — your habit is now automatic'],
            ['key' => 'habit_count_5',     'icon' => '📋', 'tier' => 'bronze',   'points' => 20,  'category' => 'habits', 'title' => 'Habit Builder',      'description' => 'Have 5 active habits'],
            ['key' => 'habit_total_30',    'icon' => '⭐', 'tier' => 'silver',   'points' => 50,  'category' => 'habits', 'title' => 'Consistent',         'description' => 'Complete habits 30 times total'],
            ['key' => 'habit_total_100',   'icon' => '🏆', 'tier' => 'gold',     'points' => 150, 'category' => 'habits', 'title' => 'Century Club',       'description' => 'Complete habits 100 times total'],
            ['key' => 'all_habits_today',  'icon' => '✅', 'tier' => 'silver',   'points' => 25,  'category' => 'habits', 'title' => 'Clean Sweep',        'description' => 'Complete every single habit in one day'],
            // ── Tasks ───────────────────────────────────────
            ['key' => 'first_task_done',   'icon' => '✅', 'tier' => 'bronze',   'points' => 10,  'category' => 'tasks',  'title' => 'Getting Things Done', 'description' => 'Complete your first task'],
            ['key' => 'tasks_total_10',    'icon' => '📌', 'tier' => 'bronze',   'points' => 20,  'category' => 'tasks',  'title' => 'Productive',          'description' => 'Complete 10 tasks'],
            ['key' => 'tasks_total_50',    'icon' => '⚡', 'tier' => 'silver',   'points' => 60,  'category' => 'tasks',  'title' => 'Task Machine',        'description' => 'Complete 50 tasks'],
            ['key' => 'tasks_total_100',   'icon' => '🦾', 'tier' => 'gold',     'points' => 150, 'category' => 'tasks',  'title' => 'Unstoppable',         'description' => 'Complete 100 tasks'],
            ['key' => 'tasks_5_today',     'icon' => '🚀', 'tier' => 'silver',   'points' => 35,  'category' => 'tasks',  'title' => 'Power Day',           'description' => 'Complete 5 tasks in a single day'],
            // ── Focus ───────────────────────────────────────
            ['key' => 'first_pomodoro',    'icon' => '⏱', 'tier' => 'bronze',   'points' => 10,  'category' => 'focus',  'title' => 'Time Keeper',         'description' => 'Complete your first Pomodoro session'],
            ['key' => 'focus_1hr_day',     'icon' => '⏰', 'tier' => 'bronze',   'points' => 20,  'category' => 'focus',  'title' => 'In the Zone',         'description' => 'Focus for 1 hour in a single day'],
            ['key' => 'focus_4hr_day',     'icon' => '🧠', 'tier' => 'silver',   'points' => 60,  'category' => 'focus',  'title' => 'Deep Work',           'description' => 'Focus for 4 hours in a single day'],
            ['key' => 'focus_total_10hr',  'icon' => '⌛', 'tier' => 'silver',   'points' => 50,  'category' => 'focus',  'title' => 'Focused Life',        'description' => 'Accumulate 10 hours of total focus'],
            ['key' => 'focus_total_50hr',  'icon' => '🏅', 'tier' => 'gold',     'points' => 150, 'category' => 'focus',  'title' => 'Flow Master',         'description' => 'Accumulate 50 hours of total focus'],
            ['key' => 'focus_10_pomodoros','icon' => '🍅', 'tier' => 'silver',   'points' => 40,  'category' => 'focus',  'title' => 'Pomodoro Pro',        'description' => 'Complete 10 Pomodoro sessions'],
        ];
    }

    // ─────────────────────────────────────────────────────────
    // SEED ACHIEVEMENTS TO DB
    // Run once: php artisan db:seed --class=AchievementSeeder
    // OR call this in a migration after creating the table
    // ─────────────────────────────────────────────────────────
    public static function seed(): void
    {
        foreach (self::definitions() as $def) {
            Achievement::firstOrCreate(['key' => $def['key']], $def);
        }
    }

    // ─────────────────────────────────────────────────────────
    // CHECK ALL ACHIEVEMENTS FOR A USER
    // Call after: completing a habit, task, or focus session
    // ─────────────────────────────────────────────────────────
    public function checkAll(User $user): array
    {
        $newlyUnlocked = [];
        $today         = today()->toDateString();

        // ── Pre-load counts (efficient — only N queries total) ─
        $totalFocusSeconds = TimerSession::where('user_id', $user->id)->where('status', 'completed')->where('mode', 'focus')->sum('actual_duration');
        $todayFocusSeconds = TimerSession::where('user_id', $user->id)->where('status', 'completed')->where('mode', 'focus')->where('session_date', $today)->sum('actual_duration');
        $totalPomodoros    = TimerSession::where('user_id', $user->id)->where('status', 'completed')->where('session_type', 'pomodoro')->count();
        $totalTasksDone    = Task::where('user_id', $user->id)->where('completed', true)->count();
        $tasksDoneToday    = Task::where('user_id', $user->id)->where('completed', true)->whereDate('completed_at', $today)->count();
        $totalHabitsDone   = \App\Models\HabitCompletion::where('user_id', $user->id)->count();
        $activeHabits      = Habit::where('user_id', $user->id)->where('is_archived', false)->count();
        $maxStreak         = Habit::where('user_id', $user->id)->max('current_streak') ?? 0;
        $habitsToday       = \App\Models\HabitCompletion::where('user_id', $user->id)->where('completed_date', $today)->count();
        $totalActiveHabits = Habit::where('user_id', $user->id)->where('is_archived', false)->count();

        // Define checks: [achievement_key => condition]
        $checks = [
            'first_habit_done'   => $totalHabitsDone  >= 1,
            'habit_streak_3'     => $maxStreak         >= 3,
            'habit_streak_7'     => $maxStreak         >= 7,
            'habit_streak_21'    => $maxStreak         >= 21,
            'habit_streak_66'    => $maxStreak         >= 66,
            'habit_count_5'      => $activeHabits      >= 5,
            'habit_total_30'     => $totalHabitsDone   >= 30,
            'habit_total_100'    => $totalHabitsDone   >= 100,
            'all_habits_today'   => $totalActiveHabits > 0 && $habitsToday >= $totalActiveHabits,

            'first_task_done'    => $totalTasksDone    >= 1,
            'tasks_total_10'     => $totalTasksDone    >= 10,
            'tasks_total_50'     => $totalTasksDone    >= 50,
            'tasks_total_100'    => $totalTasksDone    >= 100,
            'tasks_5_today'      => $tasksDoneToday    >= 5,

            'first_pomodoro'     => $totalPomodoros    >= 1,
            'focus_1hr_day'      => $todayFocusSeconds >= 3600,
            'focus_4hr_day'      => $todayFocusSeconds >= 14400,
            'focus_total_10hr'   => $totalFocusSeconds >= 36000,
            'focus_total_50hr'   => $totalFocusSeconds >= 180000,
            'focus_10_pomodoros' => $totalPomodoros    >= 10,
        ];

        // Already unlocked by this user (so we don't double-award)
        $alreadyUnlocked = UserAchievement::where('user_id', $user->id)
            ->pluck('achievement_id')
            ->toArray();

        foreach ($checks as $key => $condition) {
            if (!$condition) continue;

            $achievement = Achievement::where('key', $key)->first();
            if (!$achievement) continue;
            if (in_array($achievement->id, $alreadyUnlocked)) continue;

            // 🎉 NEW ACHIEVEMENT!
            UserAchievement::create([
                'user_id'        => $user->id,
                'achievement_id' => $achievement->id,
                'unlocked_at'    => now(),
                'seen'           => false,
            ]);

            $newlyUnlocked[] = $achievement;
        }

        return $newlyUnlocked;   // Return list so the controller can flash a success message
    }
}