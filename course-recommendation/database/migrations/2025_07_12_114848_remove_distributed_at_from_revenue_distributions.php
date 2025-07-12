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
     Schema::table('revenue_distributions', function (Blueprint $table) {
    $table->dropColumn('distributed_at');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('revenue_distributions', function (Blueprint $table) {
    $table->dropColumn('distributed_at');
});

    }
};
