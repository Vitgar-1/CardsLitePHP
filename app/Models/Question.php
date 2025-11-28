<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $table = 'questions';

    public $timestamps = false;

    protected $fillable = ['topic_id', 'question_text', 'order_num'];

    protected $casts = [
        'topic_id' => 'integer',
        'order_num' => 'integer',
    ];

    /**
     * Связь с темой
     */
    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }
}