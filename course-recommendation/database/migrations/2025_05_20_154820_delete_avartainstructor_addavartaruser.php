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
        // Alter instructors table to drop avatar column
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });

        // Alter users table to add avatar column
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar', 255)->nullable()->after('gender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse: Add avatar column back to instructors table
        Schema::table('instructors', function (Blueprint $table) {
            $table->string('avatar', 255)->nullable()->after('bio');
        });

        // Reverse: Drop avatar column from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};