<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\Task;
use App\Models\TimerSession;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * DashboardController
 * FILE PATH: app/Http/Controllers/DashboardController.php
 *
 * REPLACE your entire DashboardController with this.
 * Safe fallbacks for WaterLog and JournalEntry in case
 * those models/migrations aren't set up yet.
 */
class DashboardController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function index()
    {
        $user  = auth()->user();
        $today = today()->toDateString();

        // ── Tasks: top 5 urgent incomplete tasks ─────────────
        $tasks = Task::where('user_id', $user->id)
            ->where('completed', false)
            ->orderByRaw("CASE WHEN deadline = ? THEN 0 WHEN deadline < ? THEN 1 ELSE 2 END", [$today, $today])
            ->orderBy('deadline', 'asc')
            ->orderByRaw("CASE difficulty WHEN 'hard' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->limit(5)
            ->get(['id', 'title', 'difficulty', 'deadline', 'category', 'is_pinned']);

        // ── Habits: all active + completed_today flag ─────────
        $habits = Habit::where('user_id', $user->id)
            ->where('is_archived', false)
            ->get()
            ->map(fn($h) => array_merge($h->only([
                'id', 'title', 'habit_type', 'current_streak', 'category', 'color',
            ]), [
                'completed_today' => $h->isCompletedToday(),
            ]));

        // ── Dashboard stats ───────────────────────────────────
        $stats = $this->analytics->getDashboardStats($user);

        // ── Weekly focus data (for contribution graph) ────────
        $weeklyFocus = $this->analytics->getWeeklyFocus($user);

        // ── Contribution data: last 28 days for mini graph ────
        $contributionData = $this->getContributionData($user->id, 28);

        // ── Longest current streak across all habits ──────────
        $streakDays = Habit::where('user_id', $user->id)
            ->where('is_archived', false)
            ->max('current_streak') ?? 0;

        // ── Water glasses today ───────────────────────────────
        // Safe: returns 0 if WaterLog model/table doesn't exist yet
        $waterGlasses = $this->getWaterGlasses($user->id, $today);

        // ── Most recent journal entry ─────────────────────────
        // Safe: returns null if JournalEntry model/table doesn't exist yet
        $recentJournal = $this->getRecentJournal($user->id);

        // ── Motivational quote (static — no external API) ─────
        $quotes = [
            ['content' => 'Small daily improvements over time lead to stunning results.', 'author' => 'Robin Sharma'],
            ['content' => 'We are what we repeatedly do. Excellence is a habit.',         'author' => 'Aristotle'],
            ['content' => "Don't count the days. Make the days count.",                   'author' => 'Muhammad Ali'],
            ['content' => 'The secret of getting ahead is getting started.',               'author' => 'Mark Twain'],
            ['content' => 'Discipline is the bridge between goals and achievement.',       'author' => 'Jim Rohn'],
            ['content' => "You don't have to be great to start, but start to be great.", 'author' => 'Zig Ziglar'],
            ['content' => 'Your future is created by what you do today.',                 'author' => 'Robert Kiyosaki'],
            ['content' => 'Action is the foundational key to all success.',               'author' => 'Pablo Picasso'],
            ['content' => "It's not about perfect. It's about effort.",                   'author' => 'Jillian Michaels'],
            ['content' => 'One day or day one. You decide.',                              'author' => 'Unknown'],
            ['content' => 'Focus on being productive instead of busy.',                   'author' => 'Tim Ferriss'],
            ['content' => 'Either you run the day, or the day runs you.',                 'author' => 'Jim Rohn'],
        ];
        $dayOfYear = (int) Carbon::today()->dayOfYear;
        $quote     = $quotes[$dayOfYear % count($quotes)];

        return Inertia::render('Dashboard', [
            'user'             => $user->only('id', 'name', 'email', 'theme'),
            'stats'            => $stats,
            'tasks'            => $tasks,
            'habits'           => $habits,
            'weeklyFocus'      => $weeklyFocus,
            'contributionData' => $contributionData,
            'streakDays'       => (int) $streakDays,
            'waterGlasses'     => (int) $waterGlasses,
            'recentJournal'    => $recentJournal,
            'quote'            => $quote,
        ]);
    }

    // ── Contribution data: count of focus sessions per day ───
    private function getContributionData(int $userId, int $days = 28): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $rows = TimerSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->where('session_date', '>=', $start->toDateString())
            ->select('session_date', DB::raw('count(*) as count'))
            ->groupBy('session_date')
            ->get()
            ->keyBy('session_date');

        $counts = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $key = Carbon::today()->subDays($i)->toDateString();
            $counts[$key] = $rows->has($key) ? (int) $rows[$key]->count : 0;
        }

        return ['counts' => $counts];
    }

    // ── Safe water getter ─────────────────────────────────────
    // Returns 0 if WaterLog table doesn't exist yet
    private function getWaterGlasses(int $userId, string $date): int
    {
        try {
            // Only run if the water_logs table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('water_logs')) {
                return 0;
            }
            $log = \App\Models\WaterLog::where('user_id', $userId)
                ->where('log_date', $date)
                ->first();
            return $log ? (int) $log->glasses : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    // ── Safe journal getter ───────────────────────────────────
    // Returns null if journal_entries table doesn't exist yet
    private function getRecentJournal(int $userId): ?array
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('journal_entries')) {
                return null;
            }
            $entry = \App\Models\JournalEntry::where('user_id', $userId)
                ->orderByDesc('created_at')
                ->first(['id', 'title', 'mood', 'created_at']);
            if (!$entry) return null;
            return [
                'id'         => $entry->id,
                'title'      => $entry->title,
                'mood'       => $entry->mood,
                'created_at' => $entry->created_at->diffForHumans(),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}