<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('violations');
    }

    public function down(): void
    {
        Schema::create('violations', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('action_taken', ['warning', 'suspension', 'ban']);
            $table->text('admin_notes')->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->timestamps();
        });
    }
};
