<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic: belongs to either weekly_schedule or schedule_overrides
        Schema::create('work_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->string('schedule_type'); // 'weekly' or 'override'
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['schedule_id', 'schedule_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_sessions');
    }
};
