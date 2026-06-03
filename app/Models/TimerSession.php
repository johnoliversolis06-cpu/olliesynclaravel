<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimerSession extends Model
{
    protected $fillable = [
        'user_id', 'task_id', 'habit_id', 'session_type', 'mode',
        'planned_duration', 'actual_duration', 'paused_duration',
        'pomodoro_count', 'interruption_count', 'status',
        'started_at', 'paused_at', 'ended_at', 'session_date'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'paused_at'  => 'datetime',
        'ended_at'   => 'datetime',
        'session_date' => 'date',
    ];

    public function user()  { return $this->belongsTo(User::class); }
    public function task()  { return $this->belongsTo(Task::class); }
    public function habit() { return $this->belongsTo(Habit::class); }

    // Computed elapsed seconds at any moment
    public function getElapsedSecondsAttribute(): int
    {
        $reference = ($this->status === 'paused' && $this->paused_at) ? $this->paused_at : now();
        $raw = clone $this->started_at; 
        $rawSeconds = $raw->diffInSeconds($reference);
        return max(0, $rawSeconds - $this->paused_duration);
    }

    public function getRemainingSecondsAttribute(): int
    {
        if ($this->session_type === 'open') return 0;
        return max(0, $this->planned_duration - $this->elapsed_seconds);
    }

    public function scopeCompleted($q) { return $q->where('status', 'completed'); }
    public function scopeToday($q) { return $q->where('session_date', today()); }
    public function scopeFocusOnly($q) { return $q->where('mode', 'focus'); }
}