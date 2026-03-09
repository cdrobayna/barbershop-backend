<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday, 6 = Saturday
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_schedule');
    }
};
