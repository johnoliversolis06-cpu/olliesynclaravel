<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * JournalController
 * FILE PATH: app/Http/Controllers/JournalController.php
 * FIX: entry_date now always provided so NOT NULL constraint never fails
 */
class JournalController extends Controller
{
    public function index(Request $request)
    {
        $user   = auth()->user();
        $search = $request->get('search');
        $mood   = $request->get('mood');
        $sort   = $request->get('sort', 'newest');

        $entries = JournalEntry::where('user_id', $user->id)
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            }))
            ->when($mood, fn($q) => $q->where('mood', $mood))
            ->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total'    => JournalEntry::where('user_id', $user->id)->count(),
            'positive' => JournalEntry::where('user_id', $user->id)->whereIn('mood', ['great', 'good'])->count(),
            'negative' => JournalEntry::where('user_id', $user->id)->whereIn('mood', ['bad', 'terrible'])->count(),
            'streak'   => $this->getJournalStreak($user->id),
        ];

        return Inertia::render('Journal/Index', [
            'entries' => $entries,
            'stats'   => $stats,
            'filters' => compact('search', 'mood', 'sort'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'nullable|string',
            'mood'    => 'required|in:great,good,okay,bad,terrible',
            'tags'    => 'nullable|string|max:500',
        ]);

        JournalEntry::create([
            ...$validated,
            'user_id'    => auth()->id(),
            'entry_date' => today()->toDateString(),
        ]);

        return back()->with('success', 'Entry saved!');
    }

    public function show(JournalEntry $entry)
    {
        abort_if($entry->user_id !== auth()->id(), 403);
        return Inertia::render('Journal/Show', ['entry' => $entry]);
    }

    public function update(Request $request, JournalEntry $entry)
    {
        abort_if($entry->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'nullable|string',
            'mood'    => 'required|in:great,good,okay,bad,terrible',
            'tags'    => 'nullable|string|max:500',
        ]);

        $entry->update([
            ...$validated,
            'entry_date' => $entry->entry_date ?? today()->toDateString(),
        ]);

        return back()->with('success', 'Entry updated!');
    }

    public function destroy(JournalEntry $entry)
    {
        abort_if($entry->user_id !== auth()->id(), 403);
        $entry->delete();
        return back()->with('success', 'Entry deleted.');
    }

    private function getJournalStreak(int $userId): int
    {
        $streak    = 0;
        $checkDate = now()->startOfDay();
        while (true) {
            $exists = JournalEntry::where('user_id', $userId)
                ->whereDate('created_at', $checkDate)->exists();
            if (!$exists) break;
            $streak++;
            $checkDate->subDay();
        }
        return $streak;
    }
}