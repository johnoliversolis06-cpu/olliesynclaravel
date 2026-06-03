<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Habit
 * FILE PATH: app/Models/Habit.php
 *
 * KEY METHOD: isCompletedToday()
 * Habits automatically reset at midnight because this method
 * checks against today()->toDateString() which changes every day.
 * No cron needed — it resets purely by date math.
 */
class Habit extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'frequency',      // daily | weekly | monthly
        'habit_type',     // positive | negative
        'difficulty',     // easy | medium | hard
        'color',
        'reminder_time',  // e.g. '08:00:00' — for scheduled notifications
        'icon',
        'is_archived',
        'is_recommended',
        'current_streak',
        'longest_streak',
    ];

    protected $casts = [
        'is_archived'    => 'boolean',
        'is_recommended' => 'boolean',
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function completions()
    {
        return $this->hasMany(HabitCompletion::class);
    }

    // ── MIDNIGHT RESET: isCompletedToday() ───────────────────
    // This is the method that makes habits reset at 12 AM.
    // today()->toDateString() returns '2025-06-02' and changes
    // to '2025-06-03' the moment midnight hits — so any habit
    // not completed on the NEW date will show as not done.
    // NO cron job needed. It's purely date-based.
    public function isCompletedToday(): bool
    {
        return $this->completions()
            ->where('completed_date', today()->toDateString())
            ->exists();
    }

    // ── Load today's completions for multiple habits at once ──
    // Use this in controllers to avoid N+1 queries
    public function scopeWithTodayStatus($query)
    {
        $today = today()->toDateString();
        return $query->with(['completions' => fn($q) => $q->where('completed_date', $today)]);
    }

    // ── Check if streak is still alive ───────────────────────
    // Streak is alive if completed today OR completed yesterday
    public function isStreakAlive(): bool
    {
        $yesterday = today()->subDay()->toDateString();
        $today     = today()->toDateString();

        return $this->completions()
            ->whereIn('completed_date', [$today, $yesterday])
            ->exists();
    }
}