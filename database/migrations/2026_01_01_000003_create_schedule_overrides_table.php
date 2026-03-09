<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_working');
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_overrides');
    }
};
