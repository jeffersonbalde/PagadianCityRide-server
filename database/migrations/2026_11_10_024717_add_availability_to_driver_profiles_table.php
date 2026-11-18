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
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('status');
            $table->decimal('latitude', 10, 7)->nullable()->after('is_online');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->dateTime('located_at')->nullable()->after('longitude');

            $table->index(['is_online', 'located_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropIndex(['is_online', 'located_at']);
            $table->dropColumn(['is_online', 'latitude', 'longitude', 'located_at']);
        });
    }
};
