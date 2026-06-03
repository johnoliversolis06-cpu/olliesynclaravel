<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Habit;
use App\Services\AnalyticsService;

class RecalculateStreaks extends Command
{
    // The exact word you will type in your terminal to trigger it!
    protected $signature = 'streaks:recalculate';
    protected $description = 'Nightly process to recalculate current streak counts for active habits.';

    public function handle(AnalyticsService $analytics)
    {
        $this->info('Starting Streak Recalculation...');

        $habits = Habit::where('is_archived', false)->get();
        $bar = $this->output->createProgressBar(count($habits));

        foreach ($habits as $habit) {
            $streak = $analytics->calculateCurrentStreak($habit);
            $longest = max($habit->longest_streak, $streak);
            
            $habit->update([
                'current_streak' => $streak,
                'longest_streak' => $longest
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nAll streaks updated successfully!");
    }
}