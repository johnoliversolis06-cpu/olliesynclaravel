<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TimerSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * TaskController
 * FILE PATH: app/Http/Controllers/TaskController.php
 *
 * UPDATE: index() now passes activeSession so Tasks page
 * can prompt user before redirecting to Focus timer.
 */
class TaskController extends Controller
{
    public function index()
    {
        $user  = auth()->user();
        $today = today()->toDateString();

        $activeTasks = Task::where('user_id', $user->id)
            ->where('completed', false)
            ->orderByDesc('is_pinned')
            ->orderByRaw("CASE WHEN deadline = ? THEN 0 WHEN deadline < ? THEN 1 ELSE 2 END", [$today, $today])
            ->orderBy('deadline', 'asc')
            ->orderByRaw("CASE difficulty WHEN 'hard' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->get();

        $completedTasks = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->orderByDesc('completed_at')
            ->limit(20)
            ->get();

        $categories = Task::where('user_id', $user->id)
            ->whereNotNull('category')
            ->distinct()->pluck('category')->filter()->values();

        $stats = [
            'total'     => $activeTasks->count(),
            'overdue'   => $activeTasks->filter(fn($t) => $t->deadline && $t->deadline < $today)->count(),
            'today'     => $activeTasks->filter(fn($t) => $t->deadline === $today)->count(),
            'pinned'    => $activeTasks->where('is_pinned', true)->count(),
            'completed' => Task::where('user_id', $user->id)->where('completed', true)->count(),
        ];

        // Pass active session so Tasks page can show prompt before redirect
        $activeSession = TimerSession::where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->with('task:id,title')
            ->latest()
            ->first();

        return Inertia::render('Tasks/Index', [
            'activeTasks'    => $activeTasks,
            'completedTasks' => $completedTasks,
            'stats'          => $stats,
            'categories'     => $categories,
            'activeSession'  => $activeSession ? [
                'id'         => $activeSession->id,
                'task_id'    => $activeSession->task_id,
                'task_title' => $activeSession->task?->title,
                'status'     => $activeSession->status,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'deadline'   => 'nullable|date',
            'category'   => 'nullable|string|max:100',
            'notes'      => 'nullable|string',
        ]);

        auth()->user()->tasks()->create([
            ...$validated,
            'difficulty' => $validated['difficulty'] ?? 'medium',
            'completed'  => false,
            'is_pinned'  => false,
        ]);

        return back()->with('success', 'Task added!');
    }

    public function update(Request $request, Task $task)
    {
        abort_if($task->user_id !== auth()->id(), 403);
        $validated = $request->validate([
            'title'      => 'sometimes|string|max:255',
            'difficulty' => 'sometimes|in:easy,medium,hard',
            'deadline'   => 'sometimes|nullable|date',
            'category'   => 'sometimes|nullable|string|max:100',
            'notes'      => 'sometimes|nullable|string',
        ]);
        $task->update($validated);
        return back()->with('success', 'Task updated!');
    }

    public function complete(Task $task)
    {
        abort_if($task->user_id !== auth()->id(), 403);
        $task->update($task->completed
            ? ['completed' => false, 'completed_at' => null]
            : ['completed' => true,  'completed_at' => now()]
        );
        return back();
    }

    public function pin(Task $task)
    {
        abort_if($task->user_id !== auth()->id(), 403);
        $task->update(['is_pinned' => !$task->is_pinned]);
        return back();
    }

    public function destroy(Task $task)
    {
        abort_if($task->user_id !== auth()->id(), 403);
        $task->delete();
        return back()->with('success', 'Task deleted.');
    }

    // backward compat aliases
    public function toggleComplete(Task $task) { return $this->complete($task); }
    public function togglePin(Task $task)      { return $this->pin($task); }
}