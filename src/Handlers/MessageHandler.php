<?php

namespace CardsLite\Handlers;

use CardsLite\Database;
use CardsLite\UI;
use CardsLite\Utils;
use CardsLite\TelegramHelper;
use CardsLite\Constants;
use CardsLite\Services\GameService;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

/**
 * Обработчик текстовых сообщений
 */
class MessageHandler
{
    /**
     * Обработка текстовых сообщений
     */
    public static function handleText(Api $telegram, Update $update, array &$userStates): void
    {
        $chatId = $update->message->chat->id;
        $userId = $update->message->from->id;
        $text = $update->message->text;

        // Обработка состояний диалога
        if (isset($userStates[$userId])) {
            $handled = self::handleUserState($telegram, $chatId, $userId, $text, $userStates);
            if ($handled) {
                return;
            }
        }

        // Обработка кнопок главного меню
        if (self::handleMenuButtons($telegram, $chatId, $userId, $text, $userStates)) {
            return;
        }

        // Обработка игровых команд
        if (self::handleGameButtons($telegram, $chatId, $userId, $text)) {
            return;
        }

        // Обработка ответов на вопросы (чат)
        ChatHandler::handleMessage($telegram, $chatId, $userId, $text);
    }

    /**
     * Обработка состояний пользователя
     */
    private static function handleUserState(
        Api $telegram,
        int $chatId,
        int $userId,
        string $text,
        array &$userStates
    ): bool {
        $state = $userStates[$userId]['state'] ?? null;

        if ($state === Constants::STATE_WAITING_ROOM_ID) {
            $roomId = trim($text);
            unset($userStates[$userId]);
            GameService::joinRoom($telegram, $chatId, $userId, $roomId);
            return true;
        }

        if ($state === Constants::STATE_WAITING_TOPIC_NAME) {
            $topicName = trim($text);
            if (empty($topicName)) {
                TelegramHelper::sendMessage($telegram, $chatId, "❌ Название темы не может быть пустым. Попробуйте снова:");
                return true;
            }
            $userStates[$userId] = [
                'state' => Constants::STATE_WAITING_QUESTIONS,
                'topic_name' => $topicName
            ];
            TelegramHelper::sendMessage($telegram, $chatId,
                "✅ Название темы: <b>$topicName</b>\n\n"
                . "Шаг 2/2: Отправьте список вопросов в формате:\n"
                . "1.Первый вопрос\n"
                . "2.Второй вопрос\n"
                . "3.Третий вопрос\n\n"
                . "И так далее..."
            );
            return true;
        }

        if ($state === Constants::STATE_WAITING_QUESTIONS) {
            $questions = Utils::parseQuestions($text);

            if (empty($questions)) {
                TelegramHelper::sendMessage($telegram, $chatId,
                    "❌ Не удалось распознать вопросы.\n"
                    . "Убедитесь, что формат правильный:\n"
                    . "1.Текст вопроса\n"
                    . "2.Текст вопроса\n\n"
                    . "Попробуйте снова:"
                );
                return true;
            }

            $topicName = $userStates[$userId]['topic_name'];
            $topicId = Database::createTopic($topicName);
            $addedCount = Database::addQuestionsToTopic($topicId, $questions);

            $preview = array_slice($questions, 0, 5);
            $previewText = implode("\n", array_map(fn($i, $q) => ($i+1) . ". $q", array_keys($preview), $preview));
            $more = count($questions) > 5 ? "\n... и ещё " . (count($questions) - 5) : "";

            TelegramHelper::sendMessage($telegram, $chatId,
                "✅ Тема успешно создана!\n\n"
                . "📌 Название: <b>$topicName</b>\n"
                . "🔢 ID темы: <code>$topicId</code>\n"
                . "❓ Количество вопросов: $addedCount\n\n"
                . "Вопросы:\n$previewText $more"
            );

            unset($userStates[$userId]);
            return true;
        }

        return false;
    }

    /**
     * Обработка кнопок главного меню
     */
    private static function handleMenuButtons(
        Api $telegram,
        int $chatId,
        int $userId,
        string $text,
        array &$userStates
    ): bool {
        if ($text === "📚 Выбрать тему") {
            $topics = Database::getAllTopics();
            if (empty($topics)) {
                TelegramHelper::sendMessage($telegram, $chatId, "📭 Пока нет доступных тем для игры.");
                return true;
            }
            TelegramHelper::sendMessage($telegram, $chatId, "📚 Выберите тему:", UI::getTopicSelectionKeyboard($topics));
            return true;
        }

        if ($text === "🔗 Присоединиться") {
            TelegramHelper::sendMessage($telegram, $chatId,
                "Введите ID комнаты:\n\n"
                . "(например: <code>123456</code>)"
            );
            $userStates[$userId] = ['state' => Constants::STATE_WAITING_ROOM_ID];
            return true;
        }

        return false;
    }

    /**
     * Обработка игровых кнопок
     */
    private static function handleGameButtons(Api $telegram, int $chatId, int $userId, string $text): bool
    {
        if ($text === "▶️ Далее") {
            GameService::handleNext($telegram, $chatId, $userId);
            return true;
        }

        if ($text === "❌ Выход") {
            GameService::exitGame($telegram, $chatId, $userId);
            return true;
        }

        return false;
    }
}