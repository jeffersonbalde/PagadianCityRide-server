<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * People sign in with a phone number and a text code, so the name, email
     * and password are optional.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('name');
            $table->string('role', 20)->default('passenger')->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Rows that were created without a name, email or password make the
     * original NOT NULL columns impossible to restore, so those are left as is.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'role', 'phone_verified_at']);
        });
    }
};
