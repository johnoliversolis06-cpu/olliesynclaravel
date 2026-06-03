<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('category')->default('general');
            $table->enum('time_of_day', ['morning', 'afternoon', 'evening', 'anytime'])->default('anytime');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->date('deadline')->nullable();
            $table->boolean('completed')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->integer('priority_score')->default(0);
            $table->integer('total_focus_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed']);
            $table->index(['user_id', 'deadline']);
            $table->index(['user_id', 'is_pinned']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('tasks');
    }
};