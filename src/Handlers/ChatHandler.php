<?php

namespace CardsLite\Handlers;

use CardsLite\Database;
use CardsLite\UI;
use CardsLite\TelegramHelper;
use CardsLite\Constants;
use CardsLite\Services\GameService;
use Telegram\Bot\Api;

/**
 * Обработчик сообщений чата (ответы на вопросы)
 */
class ChatHandler
{
    /**
     * Обработка текстового сообщения в чате
     */
    public static function handleMessage(Api $telegram, int $chatId, int $userId, string $messageText): void
    {
        $room = Database::getUserAnyRoom($userId);
        if (!$room) {
            return;
        }

        $roomId = $room['id'];
        $currentQuestionIndex = $room['current_question_index'];

        Database::saveChatMessage($roomId, $userId, $currentQuestionIndex, $messageText);
        Database::setPlayerAnswered($roomId, $userId, true);

        $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);
        if (!$otherPlayerId) {
            return;
        }

        if (Database::isChatRevealed($roomId)) {
            // Чат раскрыт - просто копируем сообщение
            TelegramHelper::sendMessage($telegram, $otherPlayerId, $messageText);
            return;
        }

        // Чат еще не раскрыт
        $firstAnswered = Database::checkFirstAnsweredStatus($roomId);
        [$player1First, $player2First] = GameService::updateFirstAnswered($room, $userId, $firstAnswered);

        TelegramHelper::sendMessage($telegram, $chatId, "✅ Сообщение отправлено!", UI::getRemoveKeyboard());

        if ($player1First && $player2First) {
            GameService::revealChat($telegram, $room, $chatId, $otherPlayerId, $currentQuestionIndex);
        } else {
            TelegramHelper::sendMessage($telegram, $otherPlayerId, Constants::MSG_WAITING_FOR_ANSWER);
        }
    }

    /**
     * Обработка голосового сообщения
     */
    public static function handleVoice(Api $telegram, int $chatId, int $userId, string $voiceFileId): void
    {
        $room = Database::getUserAnyRoom($userId);
        if (!$room) {
            return;
        }

        $roomId = $room['id'];
        $currentQuestionIndex = $room['current_question_index'];

        Database::saveChatMessage($roomId, $userId, $currentQuestionIndex, null, $voiceFileId, null, 'voice');
        Database::setPlayerAnswered($roomId, $userId, true);

        $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);
        if (!$otherPlayerId) {
            return;
        }

        if (Database::isChatRevealed($roomId)) {
            // Чат раскрыт - пересылаем голосовое сообщение
            TelegramHelper::sendVoice($telegram, $otherPlayerId, $voiceFileId);
            return;
        }

        // Чат еще не раскрыт
        $firstAnswered = Database::checkFirstAnsweredStatus($roomId);
        [$player1First, $player2First] = GameService::updateFirstAnswered($room, $userId, $firstAnswered);

        TelegramHelper::sendMessage($telegram, $chatId, "✅ Голосовое сообщение отправлено!", UI::getRemoveKeyboard());

        if ($player1First && $player2First) {
            GameService::revealChat($telegram, $room, $chatId, $otherPlayerId, $currentQuestionIndex);
        } else {
            TelegramHelper::sendMessage($telegram, $otherPlayerId, Constants::MSG_WAITING_FOR_ANSWER);
        }
    }

    /**
     * Обработка видеосообщения
     */
    public static function handleVideoNote(Api $telegram, int $chatId, int $userId, string $videoNoteFileId): void
    {
        $room = Database::getUserAnyRoom($userId);
        if (!$room) {
            return;
        }

        $roomId = $room['id'];
        $currentQuestionIndex = $room['current_question_index'];

        Database::saveChatMessage($roomId, $userId, $currentQuestionIndex, null, null, $videoNoteFileId, 'video_note');
        Database::setPlayerAnswered($roomId, $userId, true);

        $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);
        if (!$otherPlayerId) {
            return;
        }

        if (Database::isChatRevealed($roomId)) {
            // Чат раскрыт - пересылаем видеосообщение
            TelegramHelper::sendVideoNote($telegram, $otherPlayerId, $videoNoteFileId);
            return;
        }

        // Чат еще не раскрыт
        $firstAnswered = Database::checkFirstAnsweredStatus($roomId);
        [$player1First, $player2First] = GameService::updateFirstAnswered($room, $userId, $firstAnswered);

        TelegramHelper::sendMessage($telegram, $chatId, "✅ Видеосообщение отправлено!", UI::getRemoveKeyboard());

        if ($player1First && $player2First) {
            GameService::revealChat($telegram, $room, $chatId, $otherPlayerId, $currentQuestionIndex);
        } else {
            TelegramHelper::sendMessage($telegram, $otherPlayerId, Constants::MSG_WAITING_FOR_ANSWER);
        }
    }
}