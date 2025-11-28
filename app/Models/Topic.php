<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $table = 'topics';

    public $timestamps = false;

    protected $fillable = ['name'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Связь с вопросами
     */
    public function questions()
    {
        return $this->hasMany(Question::class, 'topic_id');
    }

    /**
     * Связь с комнатами
     */
    public function rooms()
    {
        return $this->hasMany(Room::class, 'topic_id');
    }
}