<?php

/**
 * ══════════════════════════════════════════════════════════════
 * ACHIEVEMENTS SYSTEM — All files in one place
 * ══════════════════════════════════════════════════════════════
 *
 * FILES TO CREATE:
 *
 *  database/migrations/0010_create_achievements_tables.php  ← this section
 *  app/Models/Achievement.php                               ← this section
 *  app/Models/UserAchievement.php                           ← this section
 *  app/Services/AchievementService.php                      ← this section
 *  app/Http/Controllers/AchievementController.php           ← this section
 *
 * Split each section into its own file at the path shown above.
 */

// ════════════════════════════════════════════════════════════════
// FILE: database/migrations/0010_create_achievements_tables.php
// ════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Master list of all achievements
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();         // e.g. 'first_habit', 'streak_7'
            $table->string('title');                 // "First Steps"
            $table->text('description');             // "Complete your first habit"
            $table->string('icon')->default('🏆');   // emoji
            $table->string('category');              // 'habits' | 'tasks' | 'focus' | 'streak'
            $table->string('tier')->default('bronze'); // bronze | silver | gold | platinum
            $table->integer('points')->default(10);
            $table->timestamps();
        });

        // Which achievements each user has unlocked
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained()->onDelete('cascade');
            $table->dateTime('unlocked_at');
            $table->boolean('seen')->default(false); // for "new!" badge notification
            $table->timestamps();
            $table->unique(['user_id', 'achievement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};
