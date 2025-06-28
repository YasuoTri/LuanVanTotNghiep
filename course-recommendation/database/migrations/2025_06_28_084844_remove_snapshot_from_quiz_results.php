<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::table('quiz_results', function (Blueprint $table) {
        $table->dropColumn('snapshot_json');
    });
}

public function down(): void
{
    Schema::table('quiz_results', function (Blueprint $table) {
        $table->longText('snapshot_json')->nullable()->check('json_valid(`snapshot_json`)');
    });
}

};
