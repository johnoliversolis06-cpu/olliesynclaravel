<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * HabitCompletion
 * FILE PATH: app/Models/HabitCompletion.php
 *
 * FIX: Added habit() relationship so AnalyticsController
 * can use whereHas('habit', ...) without crashing.
 */
class HabitCompletion extends Model
{
    protected $fillable = [
        'habit_id',
        'user_id',
        'completed_date',
        'notes',
    ];

    protected $casts = [
        'completed_date' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}