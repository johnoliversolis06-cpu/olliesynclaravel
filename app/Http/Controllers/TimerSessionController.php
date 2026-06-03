<?php
namespace App\Http\Controllers;

use App\Models\TimerSession;
use App\Services\TimerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimerSessionController extends Controller
{
    public function __construct(private TimerService $timer) {}

    public function index()
    {
        $user = auth()->user();

        $activeSession = TimerSession::where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->with(['task', 'habit'])
            ->latest()
            ->first();

        $tasks = $user->tasks()->where('completed', false)->get(['id', 'title']);
        $habits = $user->habits()->where('is_archived', false)->get(['id', 'title']);

        $sessionState = $activeSession ? $this->timer->getSessionState($activeSession, $user) : null;
        $todayStats = [
            'next_count' => $this->timer->getNextPomodoroCount($user),
        ];

        return Inertia::render('Focus/Index', compact('activeSession', 'sessionState', 'tasks', 'habits', 'todayStats', 'user'));
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'session_type' => 'required|in:pomodoro,open',
            'mode'         => 'nullable|in:focus,break,long_break',
            'task_id'      => 'nullable|exists:tasks,id',
            'habit_id'     => 'nullable|exists:habits,id',
            'pomodoro_count' => 'nullable|integer',
        ]);

        $session = $this->timer->startSession(auth()->user(), $data);
        return back()->with(['session' => $session]);
    }

    public function pause(TimerSession $session)
    {
        $this->timer->pauseSession($session);
        return back();
    }

    public function resume(TimerSession $session)
    {
        $this->timer->resumeSession($session);
        return back();
    }

    public function complete(TimerSession $session)
    {
        $this->timer->completeSession($session, auth()->user());
        return back();
    }

    public function abandon(TimerSession $session)
    {
        $this->timer->abandonSession($session);
        return back();
    }

    public function state(TimerSession $session)
    {
        return response()->json($this->timer->getSessionState($session, auth()->user()));
    }
}