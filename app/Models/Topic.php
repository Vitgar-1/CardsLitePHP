<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property DateTime|null $created_at
 */
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
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'topic_id');
    }

    /**
     * Связь с комнатами
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'topic_id');
    }
}