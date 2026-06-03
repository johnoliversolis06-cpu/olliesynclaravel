<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'user_id', 'title', 'notes', 'category', 'time_of_day',
        'difficulty', 'deadline', 'completed', 'is_pinned',
        'priority_score', 'total_focus_seconds', 'completed_at'
    ];

    protected $casts = [
        'completed' => 'boolean',
        'is_pinned' => 'boolean',
        'deadline' => 'date',
        'completed_at' => 'datetime',
    ];

    public function user()          { return $this->belongsTo(User::class); }
    public function timerSessions() { return $this->hasMany(TimerSession::class); }

    // Scopes for quick filtering
    public function scopeActive($q)    { return $q->where('completed', false); }
    public function scopePinned($q)    { return $q->where('is_pinned', true); }
    public function scopeOverdue($q)   { return $q->where('deadline', '<', today())->where('completed', false); }

    // Auto-calculate priority score before saving
    protected static function booted()
    {
        static::saving(function ($task) {
            $task->priority_score = self::computePriorityScore($task);
        });
        
        static::saved(function ($task) {
            // Automatically log completed time if marked done
            if ($task->completed && !$task->completed_at) {
                $task->timestamps = false;
                $task->completed_at = now();
                $task->save();
            }
        });
    }

    public static function computePriorityScore(self $task): int
    {
        $score = 0;
        if ($task->is_pinned) $score += 100;
        
        if ($task->deadline) {
            $daysLeft = now()->diffInDays($task->deadline, false);
            $score += match(true) {
                $daysLeft < 0  => 90,
                $daysLeft <= 1 => 70,
                $daysLeft <= 3 => 50,
                $daysLeft <= 7 => 30,
                default        => 10,
            };
        }
        
        $score += match($task->difficulty) {
            'hard'   => 30,
            'medium' => 20,
            default  => 10,
        };
        return $score;
    }

    public function getIsOverdueAttribute(): bool {
        return $this->deadline && $this->deadline->isPast() && !$this->completed;
    }
    public function getFocusMinutesAttribute(): int {
        return (int) round($this->total_focus_seconds / 60);
    }
}