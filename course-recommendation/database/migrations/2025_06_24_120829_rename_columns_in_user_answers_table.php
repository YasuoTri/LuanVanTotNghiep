<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameColumnsInUserAnswersTable extends Migration
{
    public function up()
    {
        Schema::table('user_answers', function (Blueprint $table) {
            $table->renameColumn('question_id', 'question_index');
            $table->renameColumn('choice_id', 'choice_index');
        });
    }

    public function down()
    {
        Schema::table('user_answers', function (Blueprint $table) {
            $table->renameColumn('question_index', 'question_id');
            $table->renameColumn('choice_index', 'choice_id');
        });
    }
}
