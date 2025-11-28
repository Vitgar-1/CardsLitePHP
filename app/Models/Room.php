<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'topic_id', 'status', 'current_question_index',
        'player1_id', 'player2_id', 'player1_ready', 'player2_ready',
        'player1_message_id', 'player2_message_id',
        'player1_answered', 'player2_answered',
        'player1_first_answered', 'player2_first_answered',
        'chat_revealed'
    ];

    protected $casts = [
        'topic_id' => 'integer',
        'current_question_index' => 'integer',
        'player1_id' => 'integer',
        'player2_id' => 'integer',
        'player1_ready' => 'boolean',
        'player2_ready' => 'boolean',
        'player1_message_id' => 'integer',
        'player2_message_id' => 'integer',
        'player1_answered' => 'boolean',
        'player2_answered' => 'boolean',
        'player1_first_answered' => 'boolean',
        'player2_first_answered' => 'boolean',
        'chat_revealed' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Связь с темой
     */
    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    /**
     * Связь с ответами
     */
    public function answers()
    {
        return $this->hasMany(Answer::class, 'room_id');
    }

    /**
     * Связь с сообщениями чата
     */
    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class, 'room_id');
    }
}