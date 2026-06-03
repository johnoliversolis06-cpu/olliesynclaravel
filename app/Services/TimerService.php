<?php
namespace App\Services;

use App\Models\User;
use App\Models\Task;
use App\Models\HabitCompletion;
use App\Models\TimerSession;

class TimerService
{
    public function startSession(User $user, array $data): TimerSession
    {
        $this->abandonActiveSessions($user);

        $isPomodoro = $data['session_type'] === 'pomodoro';
        $mode = $data['mode'] ?? 'focus';

        $planned = 0;
        if ($isPomodoro) {
            $planned = match($mode) {
                'focus'      => $user->focus_interval * 60,
                'break'      => $user->break_interval * 60,
                'long_break' => $user->long_break_interval * 60,
                default      => $user->focus_interval * 60,
            };
        }

        return TimerSession::create([
            'user_id'          => $user->id,
            'task_id'          => $data['task_id'] ?? null,
            'habit_id'         => $data['habit_id'] ?? null,
            'session_type'     => $data['session_type'],
            'mode'             => $mode,
            'planned_duration' => $planned,
            'paused_duration'  => 0,
            'pomodoro_count'   => $data['pomodoro_count'] ?? 1,
            'status'           => 'active',
            'started_at'       => now(),
            'session_date'     => today(),
        ]);
    }

    public function pauseSession(TimerSession $session): TimerSession
    {
        $session->update([
            'status'    => 'paused',
            'paused_at' => now(),
            'interruption_count' => $session->interruption_count + 1,
        ]);
        return $session->fresh();
    }

    public function resumeSession(TimerSession $session): TimerSession
    {
        if ($session->paused_at) {
            $pausedSeconds = now()->diffInSeconds($session->paused_at);
            $session->update([
                'status'          => 'active',
                'paused_at'       => null,
                'paused_duration' => $session->paused_duration + $pausedSeconds,
            ]);
        }
        return $session->fresh();
    }

    public function completeSession(TimerSession $session, User $user): TimerSession
    {
        if ($session->status === 'paused' && $session->paused_at) {
            $pausedSeconds = now()->diffInSeconds($session->paused_at);
            $session->paused_duration += $pausedSeconds;
        }

        $elapsed = $this->calculateElapsed($session);

        if ($session->session_type === 'open' && $user->auto_cutoff_duration > 0) {
            $cutoffSeconds = $user->auto_cutoff_duration * 60;
            $elapsed = min($elapsed, $cutoffSeconds);
        }

        $session->update([
            'status'          => 'completed',
            'actual_duration' => $elapsed,
            'ended_at'        => now(),
        ]);

        if ($session->task_id && $session->mode === 'focus') {
            Task::where('id', $session->task_id)->increment('total_focus_seconds', $elapsed);
        }

        if ($session->habit_id && $session->mode === 'focus') {
            HabitCompletion::where('habit_id', $session->habit_id)
                ->where('completed_date', today()->toDateString())
                ->increment('focus_seconds', $elapsed);
        }

        return $session->fresh();
    }

    public function abandonSession(TimerSession $session): void
    {
        $session->update(['status' => 'abandoned', 'ended_at' => now()]);
    }

    public function getSessionState(TimerSession $session, User $user): array
    {
        if (!in_array($session->status, ['active', 'paused'])) {
            return ['status' => $session->status, 'elapsed' => $session->actual_duration ?? 0];
        }

        $elapsed = $this->calculateElapsed($session);
        $remaining = 0;
        $isOvertime = false;
        
        if ($session->session_type === 'pomodoro') {
            $remaining  = max(0, $session->planned_duration - $elapsed);
            $isOvertime = $elapsed > $session->planned_duration;
        }

        return [
            'session_id'      => $session->id,
            'status'          => $session->status,
            'mode'            => $session->mode,
            'session_type'    => $session->session_type,
            'elapsed'         => $elapsed,
            'elapsed_formatted' => $this->formatSeconds($elapsed),
            'remaining'       => $remaining,
            'remaining_formatted' => $this->formatSeconds($remaining),
            'planned'         => $session->planned_duration,
            'is_overtime'     => $isOvertime,
            'task'            => $session->task ? ['id' => $session->task_id, 'title' => $session->task->title] : null,
            'habit'           => $session->habit ? ['id' => $session->habit_id, 'title' => $session->habit->title] : null,
        ];
    }

    public function calculateElapsed(TimerSession $session): int
    {
        $reference = ($session->status === 'paused' && $session->paused_at) ? $session->paused_at : now();
        $raw = clone $session->started_at; 
        return max(0, $raw->diffInSeconds($reference) - $session->paused_duration);
    }

    private function abandonActiveSessions(User $user): void
    {
        TimerSession::where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->update(['status' => 'abandoned', 'ended_at' => now()]);
    }

    public function formatSeconds(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $secs    = $seconds % 60;
        return sprintf('%02d:%02d', $minutes, $secs);
    }

    public function getNextPomodoroCount(User $user): int
    {
        $lastSession = TimerSession::where('user_id', $user->id)
            ->where('session_type', 'pomodoro')
            ->where('mode', 'focus')
            ->where('status', 'completed')
            ->whereDate('session_date', today())
            ->max('pomodoro_count');

        $next = ($lastSession ?? 0) + 1;
        return $next > $user->pomodoros_before_long_break ? 1 : $next;
    }
}