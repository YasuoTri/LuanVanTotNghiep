<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Update existing data to match new ENUM values
        DB::statement("UPDATE courses SET difficulty_level = 'Beginner' WHERE difficulty_level IN ('beginner', 'easy', 'novice')");
        DB::statement("UPDATE courses SET difficulty_level = 'Intermediate' WHERE difficulty_level IN ('intermediate', 'medium')");
        DB::statement("UPDATE courses SET difficulty_level = 'Advanced' WHERE difficulty_level IN ('advanced', 'hard', 'expert')");
        // Set NULL or default for unmatched values
        DB::statement("UPDATE courses SET difficulty_level = NULL WHERE difficulty_level NOT IN ('Beginner', 'Intermediate', 'Advanced')");

        // Step 2: Change column type to ENUM
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('difficulty_level', ['Beginner', 'Intermediate', 'Advanced'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Revert back to varchar(50)
            $table->string('difficulty_level', 50)->nullable()->change();
        });
    }
};