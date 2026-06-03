<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'notify_task_due',         // daily task reminder
        'notify_habit_reminder',   // per-habit reminder
        'notify_focus_complete',   // alarm when timer ends
        'notify_daily_summary',    // end of day recap
        'notify_streak_warning',   // streak about to break
        'habit_reminder_time',     // default global time e.g. '08:00'
        'task_reminder_time',      // e.g. '07:30'
        'daily_summary_time',      // e.g. '21:00'
        'push_subscription',       // JSON: { endpoint, keys: { p256dh, auth } }
    ];

    protected $casts = [
        'notify_task_due'       => 'boolean',
        'notify_habit_reminder' => 'boolean',
        'notify_focus_complete' => 'boolean',
        'notify_daily_summary'  => 'boolean',
        'notify_streak_warning' => 'boolean',
    ];

    // ── Relationship ─────────────────────────────────────────
    public function user() { return $this->belongsTo(User::class); }

    // ── Helper: check if web push is enabled ─────────────────
    public function hasPushSubscription(): bool
    {
        return !empty($this->push_subscription);
    }

    public function getPushSubscriptionArray(): ?array
    {
        return $this->push_subscription
            ? json_decode($this->push_subscription, true)
            : null;
    }
}