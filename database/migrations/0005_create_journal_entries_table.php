<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->longText('content');
            $table->enum('mood', ['great', 'good', 'neutral', 'bad', 'awful'])->nullable();
            $table->json('tags')->nullable();
            $table->date('entry_date');
            $table->integer('word_count')->default(0);
            $table->integer('read_time_seconds')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'entry_date']);
            $table->index(['user_id', 'mood']);
            $table->text('content');

            
        });
    }

    public function down(): void {
        Schema::dropIfExists('journal_entries');
    }
};