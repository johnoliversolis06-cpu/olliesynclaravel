<?php

/**
 * routes/web.php
 * FILE PATH: routes/web.php
 *
 * BUILT FOR: OllieSync (Laravel + Inertia + Vue)
 * REPLACE your entire routes/web.php with this.
 * Clean — no duplicates, no routes from other projects.
 */

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\TimerSessionController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\WaterController;
use App\Http\Controllers\AchievementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── Public: Landing page ──────────────────────────────────────
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Welcome', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

// ── All routes below require login ────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // ── Dashboard ────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Analytics / Stats ─────────────────────────────────────
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // ── Tasks ────────────────────────────────────────────────
    Route::get   ('/tasks',                 [TaskController::class, 'index']   )->name('tasks.index');
    Route::post  ('/tasks',                 [TaskController::class, 'store']   )->name('tasks.store');
    Route::patch ('/tasks/{task}',          [TaskController::class, 'update']  )->name('tasks.update');
    Route::delete('/tasks/{task}',          [TaskController::class, 'destroy'] )->name('tasks.destroy');
    Route::patch ('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::patch ('/tasks/{task}/pin',      [TaskController::class, 'pin']     )->name('tasks.pin');

    // ── Habits ───────────────────────────────────────────────
    Route::get   ('/habits',                    [HabitController::class, 'index']         )->name('habits.index');
    Route::post  ('/habits',                    [HabitController::class, 'store']         )->name('habits.store');
    Route::patch ('/habits/{habit}',            [HabitController::class, 'update']        )->name('habits.update');
    Route::delete('/habits/{habit}',            [HabitController::class, 'destroy']       )->name('habits.destroy');
    Route::patch ('/habits/{habit}/complete',   [HabitController::class, 'complete']      )->name('habits.complete');
    Route::patch ('/habits/{habit}/archive',    [HabitController::class, 'archive']       )->name('habits.archive');
    Route::patch ('/habits/{habit}/reminder',   [HabitController::class, 'setReminder']   )->name('habits.setReminder');
    Route::delete('/habits/{habit}/reminder',   [HabitController::class, 'removeReminder'])->name('habits.removeReminder');

    // ── Focus Timer ───────────────────────────────────────────
    Route::get   ('/focus',                    [TimerSessionController::class, 'index']   )->name('focus.index');
    Route::post  ('/focus',                    [TimerSessionController::class, 'start']   )->name('focus.start');
    Route::patch ('/focus/{session}/pause',    [TimerSessionController::class, 'pause']   )->name('focus.pause');
    Route::patch ('/focus/{session}/resume',   [TimerSessionController::class, 'resume']  )->name('focus.resume');
    Route::patch ('/focus/{session}/complete', [TimerSessionController::class, 'complete'])->name('focus.complete');
    Route::patch ('/focus/{session}/abandon',  [TimerSessionController::class, 'abandon'] )->name('focus.abandon');

    // ── Journal ───────────────────────────────────────────────
    Route::get   ('/journal',         [JournalController::class, 'index']  )->name('journal.index');
    Route::post  ('/journal',         [JournalController::class, 'store']  )->name('journal.store');
    Route::get   ('/journal/{entry}', [JournalController::class, 'show']   )->name('journal.show');
    Route::patch ('/journal/{entry}', [JournalController::class, 'update'] )->name('journal.update');
    Route::delete('/journal/{entry}', [JournalController::class, 'destroy'])->name('journal.destroy');

    // ── Settings ──────────────────────────────────────────────
    Route::get  ('/settings',               [SettingsController::class, 'index']             )->name('settings.index');
    Route::patch('/settings/theme',         [SettingsController::class, 'updateTheme']        )->name('settings.theme');
    Route::patch('/settings/timer',         [SettingsController::class, 'updateTimer']        )->name('settings.timer');
    Route::patch('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');

    // ── Push Notifications ────────────────────────────────────
    Route::get   ('/notifications/vapid-key',   [NotificationController::class, 'vapidPublicKey'])->name('notifications.vapidKey');
    Route::post  ('/notifications/subscribe',   [NotificationController::class, 'subscribe']     )->name('notifications.subscribe');
    Route::delete('/notifications/unsubscribe', [NotificationController::class, 'unsubscribe']   )->name('notifications.unsubscribe');

    // ── Water Tracker ─────────────────────────────────────────
    Route::get ('/water',        [WaterController::class, 'today'] )->name('water.today');
    Route::post('/water',        [WaterController::class, 'add']   )->name('water.add');
    Route::post('/water/remove', [WaterController::class, 'remove'])->name('water.remove');

    // ── Achievements ──────────────────────────────────────────
    Route::get  ('/achievements',           [AchievementController::class, 'index']   )->name('achievements.index');
    Route::patch('/achievements/mark-seen', [AchievementController::class, 'markSeen'])->name('achievements.markSeen');

    // ── Profile (default Laravel auth — keep these) ───────────
    Route::get   ('/profile', [ProfileController::class, 'edit']   )->name('profile.edit');
    Route::patch ('/profile', [ProfileController::class, 'update'] )->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';