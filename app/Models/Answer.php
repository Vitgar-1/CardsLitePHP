<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $room_id
 * @property int $user_id
 * @property int $question_index
 * @property string $answer_text
 * @property DateTime|null $answered_at
 */
class Answer extends Model
{
    protected $table = 'answers';

    public $timestamps = false;

    protected $fillable = ['room_id', 'user_id', 'question_index', 'answer_text'];

    protected $casts = [
        'user_id' => 'integer',
        'question_index' => 'integer',
        'answered_at' => 'datetime',
    ];

    /**
     * Связь с комнатой
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}