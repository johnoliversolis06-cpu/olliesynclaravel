<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledNotification extends Model
{
    protected $fillable = [
        'user_id',
        'habit_id',
        'task_id',
        'title',
        'body',
        'type',          // 'habit_reminder' | 'streak_warning' | 'task_due' | 'daily_summary' | 'focus_complete'
        'fire_at',
        'repeat_daily',
        'repeat_time',   // e.g. '08:00:00'
        'fired_at',
        'is_active',
    ];

    protected $casts = [
        'fire_at'      => 'datetime',
        'fired_at'     => 'datetime',
        'repeat_daily' => 'boolean',
        'is_active'    => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────
    public function user()  { return $this->belongsTo(User::class); }
    public function habit() { return $this->belongsTo(Habit::class); }
    public function task()  { return $this->belongsTo(Task::class); }

    // ── Scopes ───────────────────────────────────────────────
    // Notifications that haven't been fired yet and are due
    public function scopeDueNow($query)
    {
        return $query
            ->where('is_active', true)
            ->whereNull('fired_at')
            ->where('fire_at', '<=', now());
    }

    // Only habit reminders
    public function scopeHabitReminders($query)
    {
        return $query->where('type', 'habit_reminder');
    }
}