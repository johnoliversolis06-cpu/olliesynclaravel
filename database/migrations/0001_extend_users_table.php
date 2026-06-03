<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('theme', ['light', 'dark'])->default('light')->after('password');
            $table->integer('focus_interval')->default(25)->after('theme');
            $table->integer('break_interval')->default(5)->after('focus_interval');
            $table->integer('long_break_interval')->default(15)->after('break_interval');
            $table->integer('pomodoros_before_long_break')->default(4)->after('long_break_interval');
            $table->integer('auto_cutoff_duration')->default(30)->after('pomodoros_before_long_break');
            $table->string('avatar_url')->nullable()->after('auto_cutoff_duration');
            $table->boolean('is_admin')->default(false)->after('avatar_url');
            $table->timestamp('last_active_at')->nullable()->after('is_admin');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['theme', 'focus_interval', 'break_interval', 'long_break_interval', 'pomodoros_before_long_break', 'auto_cutoff_duration', 'avatar_url', 'is_admin', 'last_active_at']);
        });
    }
};