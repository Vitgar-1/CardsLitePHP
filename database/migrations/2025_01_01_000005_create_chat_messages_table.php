<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class CreateChatMessagesTable
{
    public function up(Capsule $capsule): void
    {
        $capsule->schema()->create('chat_messages', function ($table) {
            $table->increments('id');
            $table->string('room_id', 32);
            $table->bigInteger('user_id');
            $table->integer('question_index');
            $table->string('message_type', 16)->default('text');
            $table->text('message_text')->nullable();
            $table->string('voice_file_id')->nullable();
            $table->string('video_note_file_id')->nullable();
            $table->timestamp('sent_at')->useCurrent();

            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->onDelete('cascade');

            $table->index(['room_id', 'question_index'], 'idx_chat_messages_room');
        });
    }

    public function down(Capsule $capsule): void
    {
        $capsule->schema()->dropIfExists('chat_messages');
    }
}