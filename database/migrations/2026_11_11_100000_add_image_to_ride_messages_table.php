<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. A message can be a photo, with or without words.
     */
    public function up(): void
    {
        Schema::table('ride_messages', function (Blueprint $table) {
            $table->string('body', 500)->nullable()->change();
            $table->string('image_path')->nullable()->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ride_messages', function (Blueprint $table) {
            $table->dropColumn('image_path');
            $table->string('body', 500)->nullable(false)->change();
        });
    }
};
