<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class CreateAnswersTable
{
    public function up(Capsule $capsule): void
    {
        $capsule->schema()->create('answers', function ($table) {
            $table->increments('id');
            $table->string('room_id', 32);
            $table->bigInteger('user_id');
            $table->integer('question_index');
            $table->text('answer_text');
            $table->timestamp('answered_at')->useCurrent();

            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->onDelete('cascade');

            $table->unique(['room_id', 'user_id', 'question_index'], 'uq_answer');
            $table->index(['room_id', 'question_index'], 'idx_answers_room');
        });
    }

    public function down(Capsule $capsule): void
    {
        $capsule->schema()->dropIfExists('answers');
    }
}