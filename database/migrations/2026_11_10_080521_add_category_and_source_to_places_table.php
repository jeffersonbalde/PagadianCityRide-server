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
        Schema::table('places', function (Blueprint $table) {
            $table->string('category', 20)->default('other')->after('area');
            $table->string('source', 20)->default('manual')->after('category');
            // The place in OpenStreetMap, like "node/123", so a new import updates the same row.
            $table->string('osm_id', 30)->nullable()->unique()->after('source');
            // An admin can hide a place without deleting it, and a new import does not bring it back.
            $table->boolean('is_active')->default(true)->after('osm_id');

            $table->index(['is_active', 'category']);
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'category']);
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropUnique(['osm_id']);
            $table->dropColumn(['category', 'source', 'osm_id', 'is_active']);
        });
    }
};
