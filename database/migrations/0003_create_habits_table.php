<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('health');
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('daily');
            $table->text('notes')->nullable();
            $table->enum('habit_type', ['positive', 'negative', 'both'])->default('positive');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('easy');
            $table->string('icon')->nullable();
            $table->string('color')->nullable()->default('#6366f1');
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_recommended')->default(false);
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_archived']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('habits');
    }
};