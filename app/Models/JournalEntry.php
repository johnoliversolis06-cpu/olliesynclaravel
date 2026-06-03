<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * JournalEntry
 * FILE PATH: app/Models/JournalEntry.php
 */
class JournalEntry extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'mood',    // great | good | okay | bad | terrible
        'tags',    // comma-separated string e.g. "work,personal,health"
            'entry_date',
    ];

    // ── Relationship ─────────────────────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Helper: get tags as an array ─────────────────────────
    public function getTagsArrayAttribute(): array
    {
        if (!$this->tags) return [];
        return array_filter(array_map('trim', explode(',', $this->tags)));
    }
}