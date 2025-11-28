<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class CreateRoomsTable
{
    public function up(Capsule $capsule): void
    {
        $capsule->schema()->create('rooms', function ($table) {
            $table->string('id', 32)->primary();
            $table->unsignedInteger('topic_id');
            $table->string('status', 32)->default('waiting');
            $table->integer('current_question_index')->default(0);
            $table->bigInteger('player1_id')->nullable();
            $table->bigInteger('player2_id')->nullable();
            $table->boolean('player1_ready')->default(false);
            $table->boolean('player2_ready')->default(false);
            $table->bigInteger('player1_message_id')->nullable();
            $table->bigInteger('player2_message_id')->nullable();
            $table->boolean('player1_answered')->default(false);
            $table->boolean('player2_answered')->default(false);
            $table->boolean('player1_first_answered')->default(false);
            $table->boolean('player2_first_answered')->default(false);
            $table->boolean('chat_revealed')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('topic_id')
                ->references('id')
                ->on('topics');

            $table->index(['player1_id', 'status'], 'idx_rooms_player1');
            $table->index(['player2_id', 'status'], 'idx_rooms_player2');
        });
    }

    public function down(Capsule $capsule): void
    {
        $capsule->schema()->dropIfExists('rooms');
    }
}