<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An admin can block a person who causes trouble. A blocked person cannot sign in.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('role');
            $table->string('blocked_reason', 500)->nullable()->after('blocked_at');
            $table->foreignId('blocked_by')->nullable()->after('blocked_reason')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blocked_by');
            $table->dropColumn(['blocked_at', 'blocked_reason']);
        });
    }
};
