<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['student_id', 'category_id']); // Prevent duplicate selections
        });

        // Optional: Drop the interests column from students table if no longer needed
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('interests');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_category');

        // Restore interests column if needed
        Schema::table('students', function (Blueprint $table) {
            $table->text('interests')->nullable()->after('learning_goals');
        });
    }
};