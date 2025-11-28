<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $topic_id
 * @property string $status
 * @property int $current_question_index
 * @property int $player1_id
 * @property int|null $player2_id
 * @property bool $player1_ready
 * @property bool $player2_ready
 * @property int|null $player1_message_id
 * @property int|null $player2_message_id
 * @property bool $player1_answered
 * @property bool $player2_answered
 * @property bool $player1_first_answered
 * @property bool $player2_first_answered
 * @property bool $chat_revealed
 * @property DateTime|null $created_at
 */
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
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    /**
     * Связь с ответами
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'room_id');
    }

    /**
     * Связь с сообщениями чата
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'room_id');
    }
}