<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    public $timestamps = false;

    protected $fillable = [
        'room_id', 'user_id', 'question_index', 'message_type',
        'message_text', 'voice_file_id', 'video_note_file_id'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'question_index' => 'integer',
        'sent_at' => 'datetime',
    ];

    /**
     * Связь с комнатой
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
