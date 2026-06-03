<?php

namespace App\Http\Controllers;

use App\Models\WaterLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * WaterIntakeController
 * FILE PATH: app/Http/Controllers/WaterIntakeController.php
 *
 * Tracks glasses of water drunk today.
 * Requires: migration for water_logs table (see bottom of this file)
 *
 * MIGRATION TO ADD:
 *  Schema::create('water_logs', function (Blueprint $table) {
 *      $table->id();
 *      $table->foreignId('user_id')->constrained()->onDelete('cascade');
 *      $table->date('log_date');
 *      $table->integer('glasses')->default(0);
 *      $table->timestamps();
 *      $table->unique(['user_id', 'log_date']);
 *  });
 */
class WaterIntakeController extends Controller
{
    // Get today's water count (called from Dashboard and WaterCard)
    public function today()
    {
        $log = WaterLog::firstOrCreate(
            ['user_id' => auth()->id(), 'log_date' => today()->toDateString()],
            ['glasses' => 0]
        );
        return response()->json(['glasses' => $log->glasses, 'goal' => 8]);
    }

    // Add one glass
    public function add()
    {
        $log = WaterLog::firstOrCreate(
            ['user_id' => auth()->id(), 'log_date' => today()->toDateString()],
            ['glasses' => 0]
        );
        $log->increment('glasses');
        return response()->json(['glasses' => $log->glasses]);
    }

    // Remove one glass (undo)
    public function remove()
    {
        $log = WaterLog::where('user_id', auth()->id())
            ->where('log_date', today()->toDateString())
            ->first();

        if ($log && $log->glasses > 0) {
            $log->decrement('glasses');
        }

        return response()->json(['glasses' => $log?->glasses ?? 0]);
    }
}