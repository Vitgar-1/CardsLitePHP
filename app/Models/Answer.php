<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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