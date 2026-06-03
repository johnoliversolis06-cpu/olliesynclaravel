<?php

namespace App\Http\Controllers;

use App\Models\UserNotificationPreference;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * SettingsController
 * FILE PATH: app/Http/Controllers/SettingsController.php
 */
class SettingsController extends Controller
{
    public function index()
    {
        $user   = auth()->user();
        $prefs  = UserNotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'notify_task_due'       => true,
                'notify_habit_reminder' => true,
                'notify_focus_complete' => true,
                'notify_daily_summary'  => false,
                'notify_streak_warning' => true,
                'habit_reminder_time'   => '08:00',
                'task_reminder_time'    => '07:30',
                'daily_summary_time'    => '21:00',
            ]
        );

        return Inertia::render('Settings/Index', [
            'user'       => $user->only('id','name','email','theme','focus_interval','break_interval','long_break_interval','pomodoros_before_long_break','auto_cutoff_duration'),
            'notifPrefs' => $prefs,
        ]);
    }

    // Save dark/light mode (called from AuthenticatedLayout and Settings page)
    public function updateTheme(Request $request)
    {
        $request->validate(['theme' => 'required|in:light,dark']);
        auth()->user()->update(['theme' => $request->theme]);
        return back();
    }

    // Save focus timer settings
    public function updateTimer(Request $request)
    {
        $validated = $request->validate([
            'focus_interval'              => 'required|integer|min:1|max:120',
            'break_interval'              => 'required|integer|min:1|max:60',
            'long_break_interval'         => 'required|integer|min:1|max:120',
            'pomodoros_before_long_break' => 'required|integer|min:1|max:10',
            'auto_cutoff_duration'        => 'required|integer|min:5|max:480',
        ]);

        auth()->user()->update($validated);
        return back()->with('success', 'Timer settings saved!');
    }

    // Save notification preferences
    public function updateNotifications(Request $request)
    {
        $validated = $request->validate([
            'notify_task_due'         => 'boolean',
            'notify_habit_reminder'   => 'boolean',
            'notify_focus_complete'   => 'boolean',
            'notify_daily_summary'    => 'boolean',
            'notify_streak_warning'   => 'boolean',
            'habit_reminder_time'     => 'date_format:H:i',
            'task_reminder_time'      => 'date_format:H:i',
            'daily_summary_time'      => 'date_format:H:i',
        ]);

        UserNotificationPreference::updateOrCreate(
            ['user_id' => auth()->id()],
            $validated
        );

        return back()->with('success', 'Notification settings saved!');
    }
}