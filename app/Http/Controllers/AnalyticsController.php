<?php

namespace App\Http\Controllers;

use App\Models\HabitCompletion;
use App\Models\TimerSession;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * AnalyticsController
 * FILE PATH: app/Http/Controllers/AnalyticsController.php
 *
 * UPDATE: contributionData now returns 365 days (full year)
 * for the full-year GitHub contribution graph on Analytics page.
 */
class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function index()
    {
        $user = auth()->user();

        // 1. Basic dashboard stats
        $dashboardStats = $this->analytics->getDashboardStats($user);

        // 2. Daily focus — last 7 days
        $weeklyFocus = $this->analytics->getWeeklyFocus($user);

        // 3. Good vs Bad habits per day — last 7 days
        $habitTypeWeekly = $this->getHabitTypeWeekly($user->id);

        // 4. Task category focus breakdown
        $categoryFocus = $this->getCategoryFocus($user->id);

        // 5. Full year contribution graph — 365 days
        $contributionData = $this->getContributionData($user->id, 365);

        // 6. Monthly trend — last 6 months
        $monthlyTrend = $this->getMonthlyTrend($user->id);

        return Inertia::render('Analytics/Index', compact(
            'dashboardStats',
            'weeklyFocus',
            'habitTypeWeekly',
            'categoryFocus',
            'contributionData',
            'monthlyTrend',
        ));
    }

    // ── Good vs Bad habits per day ────────────────────────────
    private function getHabitTypeWeekly(int $userId): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $key  = $date->toDateString();

            // Use JOIN instead of whereHas to avoid model relationship issues
            $positive = HabitCompletion::where('habit_completions.user_id', $userId)
                ->where('habit_completions.completed_date', $key)
                ->join('habits', 'habits.id', '=', 'habit_completions.habit_id')
                ->where('habits.habit_type', 'positive')
                ->count();

            $negative = HabitCompletion::where('habit_completions.user_id', $userId)
                ->where('habit_completions.completed_date', $key)
                ->join('habits', 'habits.id', '=', 'habit_completions.habit_id')
                ->where('habits.habit_type', 'negative')
                ->count();

            $days[] = [
                'day'      => $date->format('D'),
                'date'     => $key,
                'positive' => $positive,
                'negative' => $negative,
            ];
        }
        return $days;
    }

    // ── Focus time by task category ───────────────────────────
    private function getCategoryFocus(int $userId): array
    {
        return TimerSession::where('timer_sessions.user_id', $userId)
            ->where('status', 'completed')
            ->where('mode', 'focus')
            ->whereNotNull('timer_sessions.task_id')
            ->join('tasks', 'tasks.id', '=', 'timer_sessions.task_id')
            ->select('tasks.category', DB::raw('SUM(timer_sessions.actual_duration) as total_seconds'))
            ->groupBy('tasks.category')
            ->orderByDesc('total_seconds')
            ->limit(8)
            ->get()
            ->map(fn($row) => [
                'category'      => $row->category ?? 'General',
                'total_seconds' => (int) $row->total_seconds,
            ])
            ->toArray();
    }

    // ── Contribution data (now full 365 days) ─────────────────
    private function getContributionData(int $userId, int $days = 365): array
    {
        $start = Carbon::today()->subDays($days - 1);

        // Fetch all session counts grouped by date in one query
        $rows = TimerSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->where('session_date', '>=', $start->toDateString())
            ->select('session_date', DB::raw('count(*) as count'))
            ->groupBy('session_date')
            ->get()
            ->keyBy('session_date');

        // Build the full date map — every day filled in (0 if no sessions)
        $counts = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $key           = Carbon::today()->subDays($i)->toDateString();
            $counts[$key]  = $rows->has($key) ? (int) $rows[$key]->count : 0;
        }

        return ['counts' => $counts];
    }

    // ── Monthly focus trend ───────────────────────────────────
    private function getMonthlyTrend(int $userId): array
    {
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month        = Carbon::today()->subMonths($i)->startOfMonth();
            $totalSeconds = TimerSession::where('user_id', $userId)
                ->where('status', 'completed')
                ->where('mode', 'focus')
                ->whereYear('session_date', $month->year)
                ->whereMonth('session_date', $month->month)
                ->sum('actual_duration');

            $trend[] = [
                'month'   => $month->format('M'),
                'minutes' => (int) round($totalSeconds / 60),
            ];
        }
        return $trend;
    }
}