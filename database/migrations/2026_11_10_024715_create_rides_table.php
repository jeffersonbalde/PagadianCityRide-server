<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('requested');

            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->string('pickup_label')->nullable();
            $table->decimal('destination_latitude', 10, 7);
            $table->decimal('destination_longitude', 10, 7);
            $table->string('destination_label');

            $table->unsignedTinyInteger('passengers_count')->default(1);
            $table->string('note', 120)->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();

            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('review')->nullable();
            $table->dateTime('rated_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['passenger_id', 'status']);
            $table->index(['driver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
