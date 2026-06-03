<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('water_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('intake_date');
            $table->integer('glasses_count')->default(0);
            $table->integer('goal_glasses')->default(8);
            $table->timestamps();

            $table->unique(['user_id', 'intake_date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('water_intakes');
    }
};