<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\ScheduledNotification;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * HabitController
 * FILE PATH: app/Http/Controllers/HabitController.php
 *
 * FIX: Replaced $this->authorize() with abort_if() everywhere.
 * Laravel 11 removed AuthorizesRequests from the base Controller by default.
 * abort_if() does the same ownership check without needing a Policy file.
 */
class HabitController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    // ─────────────────────────────────────────────────────────
    // LIST ALL HABITS
    // ─────────────────────────────────────────────────────────
    public function index()
    {
        $user = auth()->user();

        $habits = Habit::where('user_id', $user->id)
            ->where('is_archived', false)
            ->orderByDesc('current_streak')
            ->orderBy('title')
            ->get()
            ->map(function ($habit) {
                return array_merge($habit->toArray(), [
                    'completed_today' => $habit->isCompletedToday(),
                    // current_streak is already a column on the model
                    // so toArray() includes it — no need to recompute
                ]);
            });

        return Inertia::render('Habits/Index', compact('habits'));
    }

    // ─────────────────────────────────────────────────────────
    // CREATE A NEW HABIT
    // ─────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'frequency'   => 'in:daily,weekly,monthly',
            'habit_type'  => 'required|in:positive,negative',
            'difficulty'  => 'in:easy,medium,hard',
            'color'       => 'nullable|string',
        ]);

        auth()->user()->habits()->create($validated);

        return back()->with('success', 'Habit added!');
    }

    // ─────────────────────────────────────────────────────────
    // DELETE A HABIT
    // FIX: was $this->authorize() — now abort_if()
    // ─────────────────────────────────────────────────────────
    public function destroy(Habit $habit)
    {
        // ✅ Simple ownership check — no Policy needed
        abort_if($habit->user_id !== auth()->id(), 403, 'That is not your habit.');

        // Remove any scheduled notifications tied to this habit
        ScheduledNotification::where('habit_id', $habit->id)
            ->where('user_id', auth()->id())
            ->delete();

        $habit->delete();

        return back()->with('success', 'Habit removed.');
    }

    // ─────────────────────────────────────────────────────────
    // MARK HABIT AS DONE (or undo if already done today)
    // FIX: was $this->authorize() — now abort_if()
    // ─────────────────────────────────────────────────────────
    public function complete(Habit $habit)
    {
        // ✅ Simple ownership check — no Policy needed
        abort_if($habit->user_id !== auth()->id(), 403, 'That is not your habit.');

        $today    = today()->toDateString();
        $existing = HabitCompletion::where('habit_id', $habit->id)
            ->where('completed_date', $today)
            ->first();

        if ($existing) {
            // Already done today — undo
            $existing->delete();

            // Roll back streak by 1 (minimum 0)
            $habit->decrement('current_streak');
            if ($habit->current_streak < 0) {
                $habit->update(['current_streak' => 0]);
            }
        } else {
            // Mark as done
            HabitCompletion::create([
                'habit_id'       => $habit->id,
                'user_id'        => auth()->id(),
                'completed_date' => $today,
            ]);

            // Increment streak
            $newStreak = ($habit->current_streak ?? 0) + 1;
            $habit->update([
                'current_streak' => $newStreak,
                'longest_streak' => max($habit->longest_streak ?? 0, $newStreak),
            ]);
        }

        return back();
    }

    // ─────────────────────────────────────────────────────────
    // SET A DAILY REMINDER FOR A HABIT
    // FIX: was $this->authorize() — now abort_if()
    // ─────────────────────────────────────────────────────────
    public function setReminder(Request $request, Habit $habit)
    {
        abort_if($habit->user_id !== auth()->id(), 403, 'That is not your habit.');

        $validated = $request->validate([
            'reminder_time' => 'required|date_format:H:i',
        ]);

        $reminderTime = $validated['reminder_time'];

        // Save on the habit row
        $habit->update(['reminder_time' => $reminderTime]);

        // When to fire: today at that time, or tomorrow if it's already past
        $fireAt = Carbon::today()->setTimeFromTimeString($reminderTime);
        if ($fireAt->isPast()) {
            $fireAt = Carbon::tomorrow()->setTimeFromTimeString($reminderTime);
        }

        // Create or replace the scheduled notification row
        ScheduledNotification::updateOrCreate(
            [
                'user_id'  => auth()->id(),
                'habit_id' => $habit->id,
                'type'     => 'habit_reminder',
            ],
            [
                'title'        => "Time for: {$habit->title}! 🔔",
                'body'         => $habit->habit_type === 'negative'
                    ? "Stay strong! Resist \"{$habit->title}\" today. 💪"
                    : "Don't forget your habit: \"{$habit->title}\" ✅",
                'fire_at'      => $fireAt,
                'repeat_daily' => true,
                'repeat_time'  => $reminderTime . ':00',
                'fired_at'     => null,
                'is_active'    => true,
            ]
        );

        $displayTime = Carbon::parse("2000-01-01 {$reminderTime}")->format('g:i A');
        return back()->with('success', "Reminder set for {$displayTime} every day.");
    }

    // ─────────────────────────────────────────────────────────
    // REMOVE REMINDER FROM A HABIT
    // FIX: was $this->authorize() — now abort_if()
    // ─────────────────────────────────────────────────────────
    public function removeReminder(Habit $habit)
    {
        abort_if($habit->user_id !== auth()->id(), 403, 'That is not your habit.');

        $habit->update(['reminder_time' => null]);

        ScheduledNotification::where('habit_id', $habit->id)
            ->where('user_id', auth()->id())
            ->where('type', 'habit_reminder')
            ->delete();

        return back()->with('success', 'Reminder removed.');
    }

    // ─────────────────────────────────────────────────────────
    // ARCHIVE A HABIT (soft-hide without deleting)
    // ─────────────────────────────────────────────────────────
    public function archive(Habit $habit)
    {
        abort_if($habit->user_id !== auth()->id(), 403, 'That is not your habit.');
        $habit->update(['is_archived' => true]);
        return back()->with('success', 'Habit archived.');
    }
}