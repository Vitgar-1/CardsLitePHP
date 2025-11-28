<?php

namespace CardsLite\Services;

use CardsLite\Database;
use CardsLite\UI;
use CardsLite\TelegramHelper;
use CardsLite\Constants;
use Telegram\Bot\Api;

/**
 * Сервис для управления игровой логикой
 */
class GameService
{
    /**
     * Обновление статуса "первый ответивший" для игрока
     */
    public static function updateFirstAnswered(array $room, int $userId, array $firstAnswered): array
    {
        [$player1First, $player2First] = $firstAnswered;

        if ($userId == $room['player1_id'] && !$player1First) {
            Database::setPlayerFirstAnswered($room['id'], $userId, true);
            $player1First = true;
        } elseif ($userId == $room['player2_id'] && !$player2First) {
            Database::setPlayerFirstAnswered($room['id'], $userId, true);
            $player2First = true;
        }

        return [$player1First, $player2First];
    }

    /**
     * Отправка медиа-файла обоим игрокам
     */
    public static function sendMediaToPlayers(Api $telegram, array $msg, int $chatId, int $otherPlayerId): void
    {
        $msgType = $msg['message_type'] ?? 'text';

        if ($msgType === 'voice' && !empty($msg['voice_file_id'])) {
            TelegramHelper::sendVoice($telegram, $chatId, $msg['voice_file_id']);
            TelegramHelper::sendVoice($telegram, $otherPlayerId, $msg['voice_file_id']);
        } elseif ($msgType === 'video_note' && !empty($msg['video_note_file_id'])) {
            TelegramHelper::sendVideoNote($telegram, $chatId, $msg['video_note_file_id']);
            TelegramHelper::sendVideoNote($telegram, $otherPlayerId, $msg['video_note_file_id']);
        }
    }

    /**
     * Раскрытие чата после того, как оба игрока ответили
     */
    public static function revealChat(
        Api $telegram,
        array $room,
        int $chatId,
        int $otherPlayerId,
        int $currentQuestionIndex
    ): void {
        $roomId = $room['id'];
        $chatMessages = Database::getChatMessages($roomId, $currentQuestionIndex);

        // Отправляем медиафайлы обоим игрокам
        foreach ($chatMessages as $msg) {
            self::sendMediaToPlayers($telegram, $msg, $chatId, $otherPlayerId);
        }

        // Отправляем текстовую историю чата
        $chatHistory = UI::formatChatHistory($chatMessages, $room['player1_id']);
        TelegramHelper::sendMessage($telegram, $chatId, $chatHistory);
        TelegramHelper::sendMessage($telegram, $otherPlayerId, $chatHistory);

        Database::setChatRevealed($roomId);

        // Сообщаем обоим игрокам, что чат открыт
        TelegramHelper::sendMessage($telegram, $chatId, Constants::MSG_CHAT_REVEALED, UI::getGameNextKeyboard());
        TelegramHelper::sendMessage($telegram, $otherPlayerId, Constants::MSG_CHAT_REVEALED, UI::getGameNextKeyboard());
    }

    /**
     * Присоединение к комнате
     */
    public static function joinRoom(Api $telegram, int $chatId, int $userId, string $roomId): void
    {
        if (Database::hasActiveRoom($userId)) {
            TelegramHelper::sendMessage($telegram, $chatId,
                "⚠️ У вас уже есть активная комната.\n"
                . "Завершите текущую игру перед присоединением к новой."
            );
            return;
        }

        $success = Database::joinRoom($roomId, $userId);

        if (!$success) {
            TelegramHelper::sendMessage($telegram, $chatId,
                "❌ Не удалось присоединиться к комнате.\n"
                . "Возможные причины:\n"
                . "- Комната не существует\n"
                . "- В комнате уже два игрока\n"
                . "- Это ваша собственная комната"
            );
            return;
        }

        $room = Database::getRoom($roomId);
        $player1Id = $room['player1_id'];
        $topicId = $room['topic_id'];
        $topic = Database::getTopicById($topicId);
        $topicName = $topic['name'] ?? "Неизвестная тема";
        $totalQuestions = Database::getTotalQuestionsCount($topicId);

        TelegramHelper::sendMessage($telegram, $chatId,
            "✅ Вы присоединились к игре!\n\n"
            . "🎯 Тема: <b>$topicName</b>\n"
            . "❓ Вопросов: $totalQuestions\n\n"
            . "🎮 Игра начинается!"
        );

        // Уведомление первому игроку
        TelegramHelper::sendMessage($telegram, $player1Id,
            "✅ К вашей комнате присоединился собеседник!\n\n"
            . "🎯 Тема: <b>$topicName</b>\n"
            . "❓ Вопросов: $totalQuestions\n\n"
            . "🎮 Игра начинается!"
        );

        // Отправляем первый вопрос обоим игрокам
        $questionText = Database::getQuestionByIndex($topicId, 0);
        if ($questionText) {
            $questionMessage = "➡️ Вопрос 1/$totalQuestions:\n\n"
                . "<b>$questionText</b>\n\n"
                . "💬 Напишите ваш ответ:";

            TelegramHelper::sendMessage($telegram, $chatId, $questionMessage);
            TelegramHelper::sendMessage($telegram, $player1Id, $questionMessage);
        } else {
            error_log("Не найден вопрос для темы $topicId с индексом 0");
        }
    }

    /**
     * Переход к следующему вопросу
     */
    public static function handleNext(Api $telegram, int $chatId, int $userId): void
    {
        $room = Database::getUserActiveRoom($userId);
        if (!$room) {
            return;
        }

        $roomId = $room['id'];
        $currentQuestionIndex = $room['current_question_index'];
        $topicId = $room['topic_id'];

        [$player1Answered, $player2Answered] = Database::checkAnswerStatus($roomId);

        if ($userId == $room['player1_id'] && !$player1Answered) {
            TelegramHelper::sendMessage($telegram, $chatId, Constants::MSG_ANSWER_FIRST);
            return;
        } elseif ($userId == $room['player2_id'] && !$player2Answered) {
            TelegramHelper::sendMessage($telegram, $chatId, Constants::MSG_ANSWER_FIRST);
            return;
        }

        Database::setPlayerReady($roomId, $userId, true);
        $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);

        if (Database::checkBothReady($roomId)) {
            $hasNext = Database::moveToNextQuestion($roomId);

            if ($hasNext) {
                $nextQuestionIndex = $currentQuestionIndex + 1;
                Database::resetChatForQuestion($roomId);

                $totalQuestions = Database::getTotalQuestionsCount($topicId);
                $nextQuestionText = Database::getQuestionByIndex($topicId, $nextQuestionIndex);

                $questionMessage = "➡️ Переходим к следующему вопросу!\n\n"
                    . "❓ Вопрос " . ($nextQuestionIndex + 1) . "/$totalQuestions:\n\n"
                    . "<b>$nextQuestionText</b>\n\n"
                    . "💬 Напишите ваш ответ:";

                TelegramHelper::sendMessage($telegram, $chatId, $questionMessage);
                if ($otherPlayerId) {
                    TelegramHelper::sendMessage($telegram, $otherPlayerId, $questionMessage);
                }
            } else {
                $topic = Database::getTopicById($topicId);
                $finishMsg = UI::formatFinishMessage($topic['name']);
                Database::deleteRoom($roomId);

                TelegramHelper::sendMessage($telegram, $chatId, $finishMsg, UI::getRemoveKeyboard());
                if ($otherPlayerId) {
                    TelegramHelper::sendMessage($telegram, $otherPlayerId, $finishMsg);
                }
            }
        } else {
            TelegramHelper::sendMessage($telegram, $chatId,
                "✅ Вы готовы к следующему вопросу!\n"
                . "⏳ Ожидаем готовности собеседника...",
                UI::getRemoveKeyboard()
            );

            if ($otherPlayerId) {
                TelegramHelper::sendMessage($telegram, $otherPlayerId, "💬 Собеседник готов к следующему вопросу!");
            }
        }
    }

    /**
     * Выход из игры
     */
    public static function exitGame(Api $telegram, int $chatId, int $userId): void
    {
        $room = Database::getUserAnyRoom($userId);
        if (!$room) {
            return;
        }

        $roomId = $room['id'];
        $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);
        Database::deleteRoom($roomId);

        TelegramHelper::sendMessage($telegram, $chatId, Constants::MSG_EXIT_GAME, UI::getRemoveKeyboard());

        if ($otherPlayerId) {
            TelegramHelper::sendMessage($telegram, $otherPlayerId, Constants::MSG_PARTNER_LEFT);
        }
    }
}