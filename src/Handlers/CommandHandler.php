<?php

namespace CardsLite\Handlers;

use CardsLite\Database;
use CardsLite\UI;
use CardsLite\Utils;
use CardsLite\TelegramHelper;
use CardsLite\Services\GameService;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

/**
 * Обработчик команд бота
 */
class CommandHandler
{
    /**
     * Обработка команды /start
     */
    public static function handleStart(Api $telegram, Update $update): void
    {
        $chatId = $update->message->chat->id;

        $welcomeText = "👋 Добро пожаловать в бот для знакомства через мини-игры!\n\n"
            . "🎯 Как это работает:\n"
            . "1️⃣ Выберите тему\n"
            . "2️⃣ Создайте комнату или присоединитесь\n"
            . "3️⃣ Отвечайте на вопросы вместе\n"
            . "4️⃣ Узнавайте друг друга!\n\n"
            . "Начнём? 👇";

        TelegramHelper::sendMessage($telegram, $chatId, $welcomeText, UI::getMainMenuKeyboard());
    }

    /**
     * Обработка команды /topics
     */
    public static function handleTopics(Api $telegram, Update $update): void
    {
        $chatId = $update->message->chat->id;
        $topics = Database::getAllTopics();

        if (empty($topics)) {
            TelegramHelper::sendMessage($telegram, $chatId, "📭 Пока нет доступных тем для игры.");
            return;
        }

        $topicsText = "📚 Доступные темы:\n\n";
        foreach ($topics as $topic) {
            $topicsText .= "{$topic['id']}. {$topic['name']}\n";
        }
        $topicsText .= "\n💡 Используйте /create_room &lt;topic_id&gt; чтобы создать комнату";

        TelegramHelper::sendMessage($telegram, $chatId, $topicsText);
    }

    /**
     * Обработка команды /create_room
     */
    public static function handleCreateRoom(Api $telegram, Update $update, array $args): void
    {
        $chatId = $update->message->chat->id;
        $userId = $update->message->from->id;

        if (empty($args)) {
            TelegramHelper::sendMessage($telegram, $chatId,
                "❌ Укажите ID темы.\n"
                . "Использование: /create_room &lt;topic_id&gt;\n"
                . "Посмотрите доступные темы: /topics"
            );
            return;
        }

        $topicId = (int)$args[0];
        $topic = Database::getTopicById($topicId);

        if (!$topic) {
            TelegramHelper::sendMessage($telegram, $chatId, "❌ Тема с ID $topicId не найдена");
            return;
        }

        if (Database::hasActiveRoom($userId)) {
            TelegramHelper::sendMessage($telegram, $chatId,
                "⚠️ У вас уже есть активная комната.\n"
                . "Завершите текущую игру перед созданием новой."
            );
            return;
        }

        $roomId = Database::createRoom($topicId, $userId);
        $topicName = $topic['name'];

        TelegramHelper::sendMessage($telegram, $chatId,
            "✅ Комната создана!\n\n"
            . "🎯 Тема: $topicName\n"
            . "🔑 ID комнаты: <code>$roomId</code>\n\n"
            . "📤 Передайте этот ID собеседнику.\n"
            . "Он должен использовать команду:\n"
            . "/join_room $roomId"
        );
    }

    /**
     * Обработка команды /join_room
     */
    public static function handleJoinRoom(Api $telegram, Update $update, array $args, array &$userStates): void
    {
        $chatId = $update->message->chat->id;
        $userId = $update->message->from->id;

        if (empty($args)) {
            TelegramHelper::sendMessage($telegram, $chatId,
                "Введите ID комнаты:\n\n"
                . "(например: <code>123456</code>)",
                UI::getRemoveKeyboard()
            );
            $userStates[$userId] = ['state' => 'waiting_room_id'];
            return;
        }

        $roomId = trim($args[0]);
        GameService::joinRoom($telegram, $chatId, $userId, $roomId);
    }

    /**
     * Обработка команды /stop
     */
    public static function handleStop(Api $telegram, Update $update): void
    {
        $chatId = $update->message->chat->id;
        $userId = $update->message->from->id;

        $room = Database::getUserActiveRoom($userId);

        if (!$room) {
            TelegramHelper::sendMessage($telegram, $chatId,
                "❌ Вы не находитесь в активной игре.\n"
                . "Создайте комнату или присоединитесь к существующей."
            );
            return;
        }

        $roomId = $room['id'];
        $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);

        Database::closeRoom($roomId);

        TelegramHelper::sendMessage($telegram, $chatId,
            "👋 Вы покинули игровую комнату.\n\n"
            . "💡 Что дальше?\n"
            . "/topics - посмотреть доступные темы\n"
            . "/create_room &lt;topic_id&gt; - создать новую комнату\n"
            . "/join_room &lt;room_id&gt; - присоединиться к комнате",
            UI::getRemoveKeyboard()
        );

        if ($otherPlayerId) {
            TelegramHelper::sendMessage($telegram, $otherPlayerId,
                "⚠️ Ваш собеседник покинул игровую комнату.\n\n"
                . "💡 Что дальше?\n"
                . "/topics - посмотреть доступные темы\n"
                . "/create_room &lt;topic_id&gt; - создать новую комнату\n"
                . "/join_room &lt;room_id&gt; - присоединиться к комнате"
            );
        }
    }

    /**
     * Обработка команды /add_topic (только для админа)
     */
    public static function handleAddTopic(Api $telegram, Update $update, int $adminId, array &$userStates): void
    {
        $chatId = $update->message->chat->id;
        $userId = $update->message->from->id;

        if (!Utils::isAdmin($userId, $adminId)) {
            TelegramHelper::sendMessage($telegram, $chatId, "❌ Команда доступна только администратору");
            return;
        }

        TelegramHelper::sendMessage($telegram, $chatId,
            "📝 Создание новой темы\n\n"
            . "Шаг 1/2: Введите название темы (одной строкой):"
        );

        $userStates[$userId] = ['state' => 'waiting_topic_name'];
    }
}