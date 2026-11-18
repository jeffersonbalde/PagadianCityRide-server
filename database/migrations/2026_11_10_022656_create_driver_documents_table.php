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
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('path');
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['driver_profile_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
    }
};
