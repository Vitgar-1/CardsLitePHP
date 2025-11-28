<?php

namespace CardsLite;

use App\Models\Topic;
use App\Models\Question;
use App\Models\Room;
use App\Models\Answer;
use App\Models\ChatMessage;

/**
 * Модуль для работы с БД через Eloquent ORM
 */
class Database
{
    // ==================== TOPICS ====================

    public static function getAllTopics(): array
    {
        return Topic::query()->orderBy('id')->get()->toArray();
    }

    public static function getTopicById(int $topicId): ?array
    {
        $topic = Topic::query()->find($topicId);
        return $topic?->toArray();
    }

    public static function createTopic(string $name): int
    {
        $topic = Topic::query()->create(['name' => $name]);
        return $topic->id;
    }

    // ==================== QUESTIONS ====================

    public static function addQuestionsToTopic(int $topicId, array $questions): int
    {
        $count = 0;
        foreach ($questions as $index => $questionText) {
            Question::query()->create([
                'topic_id' => $topicId,
                'question_text' => $questionText,
                'order_num' => $index
            ]);
            $count++;
        }
        return $count;
    }

    public static function getQuestionByIndex(int $topicId, int $index): ?string
    {
        $question = Question::query()->where('topic_id', $topicId)
            ->where('order_num', $index)
            ->first();

        return $question?->question_text;
    }

    public static function getTotalQuestionsCount(int $topicId): int
    {
        return Question::query()->where('topic_id', $topicId)->count();
    }

    // ==================== ROOMS ====================

    public static function createRoom(int $topicId, int $userId): string
    {
        $roomId = (string) rand(100000, 999999);

        Room::query()->create([
            'id' => $roomId,
            'topic_id' => $topicId,
            'player1_id' => $userId,
            'status' => 'waiting'
        ]);

        return $roomId;
    }

    public static function joinRoom(string $roomId, int $userId): bool
    {
        $room = Room::query()->find($roomId);

        if (!$room || $room->status !== 'waiting' || $room->player1_id == $userId) {
            return false;
        }

        $room->update([
            'player2_id' => $userId,
            'status' => 'active'
        ]);

        return true;
    }

    public static function getRoom(string $roomId): ?array
    {
        $room = Room::query()->find($roomId);
        return $room?->toArray();
    }

    public static function hasActiveRoom(int $userId): bool
    {
        return Room::query()->where(function($query) use ($userId) {
            $query->where('player1_id', $userId)
                  ->orWhere('player2_id', $userId);
        })
        ->whereIn('status', ['waiting', 'active'])
        ->exists();
    }

    public static function getUserActiveRoom(int $userId): ?array
    {
        $room = Room::query()->where(function($query) use ($userId) {
            $query->where('player1_id', $userId)
                  ->orWhere('player2_id', $userId);
        })
        ->where('status', 'active')
        ->first();

        return $room?->toArray();
    }

    public static function getUserAnyRoom(int $userId): ?array
    {
        $room = Room::query()->where(function($query) use ($userId) {
            $query->where('player1_id', $userId)
                  ->orWhere('player2_id', $userId);
        })
        ->whereIn('status', ['waiting', 'active'])
        ->first();

        return $room?->toArray();
    }

    public static function getOtherPlayerId(string $roomId, int $userId): ?int
    {
        $room = Room::query()->find($roomId);

        if (!$room) {
            return null;
        }

        if ($room->player1_id == $userId) {
            return $room->player2_id;
        } elseif ($room->player2_id == $userId) {
            return $room->player1_id;
        }

        return null;
    }

    public static function closeRoom(string $roomId): void
    {
        $room = Room::query()->find($roomId);
        $room?->update(['status' => 'finished']);
    }

    public static function deleteRoom(string $roomId): void
    {
        Room::destroy($roomId);
    }

    // ==================== ROOM STATE ====================

    public static function setPlayerReady(string $roomId, int $userId, bool $ready): void
    {
        $room = Room::query()->find($roomId);
        if (!$room) return;

        if ($room->player1_id == $userId) {
            $room->update(['player1_ready' => $ready]);
        } elseif ($room->player2_id == $userId) {
            $room->update(['player2_ready' => $ready]);
        }
    }

    public static function checkBothReady(string $roomId): bool
    {
        $room = Room::query()->find($roomId);
        return $room && $room->player1_ready && $room->player2_ready;
    }

    public static function setPlayerAnswered(string $roomId, int $userId, bool $answered): void
    {
        $room = Room::query()->find($roomId);
        if (!$room) return;

        if ($room->player1_id == $userId) {
            $room->update(['player1_answered' => $answered]);
        } elseif ($room->player2_id == $userId) {
            $room->update(['player2_answered' => $answered]);
        }
    }

    public static function checkAnswerStatus(string $roomId): array
    {
        $room = Room::query()->find($roomId);
        return $room ?
            [$room->player1_answered, $room->player2_answered] :
            [false, false];
    }

    public static function setPlayerFirstAnswered(string $roomId, int $userId, bool $first): void
    {
        $room = Room::query()->find($roomId);
        if (!$room) return;

        if ($room->player1_id == $userId) {
            $room->update(['player1_first_answered' => $first]);
        } elseif ($room->player2_id == $userId) {
            $room->update(['player2_first_answered' => $first]);
        }
    }

    public static function checkFirstAnsweredStatus(string $roomId): array
    {
        $room = Room::query()->find($roomId);
        return $room ?
            [$room->player1_first_answered, $room->player2_first_answered] :
            [false, false];
    }

    public static function setChatRevealed(string $roomId): void
    {
        $room = Room::query()->find($roomId);
        if ($room) {
            $room->update(['chat_revealed' => true]);
        }
    }

    public static function isChatRevealed(string $roomId): bool
    {
        $room = Room::query()->find($roomId);
        return $room ? $room->chat_revealed : false;
    }

    public static function moveToNextQuestion(string $roomId): bool
    {
        $room = Room::query()->find($roomId);
        if (!$room) return false;

        $totalQuestions = self::getTotalQuestionsCount($room->topic_id);
        $nextIndex = $room->current_question_index + 1;

        if ($nextIndex >= $totalQuestions) {
            return false;
        }

        $room->update(['current_question_index' => $nextIndex]);
        return true;
    }

    public static function resetChatForQuestion(string $roomId): void
    {
        $room = Room::query()->find($roomId);
        if (!$room) return;

        $room->update([
            'player1_ready' => false,
            'player2_ready' => false,
            'player1_answered' => false,
            'player2_answered' => false,
            'player1_first_answered' => false,
            'player2_first_answered' => false,
            'chat_revealed' => false
        ]);
    }

    // ==================== CHAT MESSAGES ====================

    public static function saveChatMessage(
        string $roomId,
        int $userId,
        int $questionIndex,
        ?string $messageText = null,
        ?string $voiceFileId = null,
        ?string $videoNoteFileId = null,
        string $messageType = 'text'
    ): void {
        ChatMessage::query()->create([
            'room_id' => $roomId,
            'user_id' => $userId,
            'question_index' => $questionIndex,
            'message_type' => $messageType,
            'message_text' => $messageText,
            'voice_file_id' => $voiceFileId,
            'video_note_file_id' => $videoNoteFileId
        ]);
    }

    public static function getChatMessages(string $roomId, int $questionIndex): array
    {
        return ChatMessage::query()->where('room_id', $roomId)
            ->where('question_index', $questionIndex)
            ->orderBy('id')
            ->get()
            ->toArray();
    }
}