<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'theme', 'focus_interval',
        'break_interval', 'long_break_interval', 'pomodoros_before_long_break',
        'auto_cutoff_duration', 'avatar_url', 'is_admin', 'last_active_at'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    public function tasks()          { return $this->hasMany(Task::class); }
    public function habits()         { return $this->hasMany(Habit::class); }
    public function journalEntries() { return $this->hasMany(JournalEntry::class); }
    public function timerSessions()  { return $this->hasMany(TimerSession::class); }
    public function waterIntakes()   { return $this->hasMany(WaterIntake::class); }
    public function habitCompletions(){ return $this->hasMany(HabitCompletion::class); }

    public function getFocusIntervalSecondsAttribute(): int {
        return $this->focus_interval * 60;
    }
    public function getBreakIntervalSecondsAttribute(): int {
        return $this->break_interval * 60;
    }
}