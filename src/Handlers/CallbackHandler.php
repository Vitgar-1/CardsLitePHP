<?php

namespace CardsLite\Handlers;

use CardsLite\Database;
use CardsLite\TelegramHelper;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;

/**
 * Обработчик callback queries (inline кнопки)
 */
class CallbackHandler
{
    /**
     * Обработка callback query
     * @throws TelegramSDKException
     */
    public static function handle(Api $telegram, Update $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $data = $callbackQuery->data;
        $chatId = $callbackQuery->message->chat->id;
        $userId = $callbackQuery->from->id;

        $telegram->answerCallbackQuery(['callback_query_id' => $callbackQuery->id]);

        if (str_starts_with($data, 'select_topic_')) {
            self::handleTopicSelection($telegram, $callbackQuery, $chatId, $userId, $data);
        }
    }

    /**
     * Обработка выбора темы
     */
    private static function handleTopicSelection(
        Api $telegram,
        $callbackQuery,
        int $chatId,
        int $userId,
        string $data
    ): void {
        $topicId = (int)str_replace('select_topic_', '', $data);

        if (Database::hasActiveRoom($userId)) {
            TelegramHelper::editMessageText(
                $telegram,
                $chatId,
                $callbackQuery->message->messageId,
                "⚠️ У вас уже есть активная комната.\nЗавершите текущую игру перед созданием новой."
            );
            return;
        }

        $topic = Database::getTopicById($topicId);
        if (!$topic) {
            TelegramHelper::editMessageText(
                $telegram,
                $chatId,
                $callbackQuery->message->messageId,
                "❌ Тема не найдена"
            );
            return;
        }

        $roomId = Database::createRoom($topicId, $userId);
        $topicName = $topic['name'];

        TelegramHelper::editMessageText(
            $telegram,
            $chatId,
            $callbackQuery->message->messageId,
            "✅ Комната создана!\n\n"
            . "🎯 Тема: $topicName\n"
            . "🔑 ID комнаты: <code>$roomId</code>\n\n"
            . "📤 Передайте этот ID собеседнику или используйте команду:\n"
            . "<code>/join_room $roomId</code>"
        );
    }
}