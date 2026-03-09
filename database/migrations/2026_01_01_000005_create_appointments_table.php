<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('party_size')->default(1);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('reschedule_requested_by')->nullable();
            $table->timestamp('reschedule_requested_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'scheduled_at']);
            $table->index(['client_id', 'status']);
            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
