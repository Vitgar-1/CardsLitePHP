<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class CreateQuestionsTable
{
    public function up(Capsule $capsule): void
    {
        $capsule->schema()->create('questions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('topic_id');
            $table->text('question_text');
            $table->integer('order_num');

            $table->foreign('topic_id')
                ->references('id')
                ->on('topics')
                ->onDelete('cascade');

            $table->unique(['topic_id', 'order_num'], 'uq_topic_order');
        });
    }

    public function down(Capsule $capsule): void
    {
        $capsule->schema()->dropIfExists('questions');
    }
}