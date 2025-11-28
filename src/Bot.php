<?php

namespace CardsLite;

use CardsLite\Handlers\CommandHandler;
use CardsLite\Handlers\MessageHandler;
use CardsLite\Handlers\ChatHandler;
use CardsLite\Handlers\CallbackHandler;
use Exception;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;

/**
 * Основной класс бота
 */
class Bot
{
    private Api $telegram;
    private int $adminId;
    private array $userStates = [];
    private int $offset = 0;

    /**
     * @throws TelegramSDKException
     */
    public function __construct(string $botToken, int $adminId)
    {
        $this->telegram = new Api($botToken);
        $this->adminId = $adminId;
    }

    /**
     * Запуск бота в режиме long polling
     */
    public function run(): void
    {
        echo "🤖 Бот запущен\n";

        while (true) {
            try {
                $this->processUpdates();
            } catch (Exception $e) {
                error_log("Ошибка в основном цикле: " . $e->getMessage());
                sleep(5);
            }
        }
    }

    /**
     * Обработка обновлений
     * @throws TelegramSDKException
     */
    private function processUpdates(): void
    {
        $updates = $this->telegram->getUpdates(['offset' => $this->offset, 'timeout' => 30]);

        foreach ($updates as $update) {
            $this->offset = $update->updateId + 1;

            if ($update->message) {
                $this->handleMessage($update);
            } elseif ($update->callbackQuery) {
                CallbackHandler::handle($this->telegram, $update);
            }
        }
    }

    /**
     * Обработка сообщения
     */
    private function handleMessage($update): void
    {
        $message = $update->message;

        // Проверка на голосовое сообщение
        if ($message->voice) {
            ChatHandler::handleVoice(
                $this->telegram,
                $message->chat->id,
                $message->from->id,
                $message->voice->fileId
            );
            return;
        }

        // Проверка на видеосообщение
        if ($message->videoNote) {
            ChatHandler::handleVideoNote(
                $this->telegram,
                $message->chat->id,
                $message->from->id,
                $message->videoNote->fileId
            );
            return;
        }

        $text = $message->text ?? '';

        // Обработка команд
        if (str_starts_with($text, '/')) {
            $this->handleCommand($update, $text);
            return;
        }

        // Обработка обычных текстовых сообщений
        MessageHandler::handleText($this->telegram, $update, $this->userStates);
    }

    /**
     * Обработка команд
     */
    private function handleCommand($update, string $text): void
    {
        if (str_starts_with($text, '/start')) {
            CommandHandler::handleStart($this->telegram, $update);
        } elseif (str_starts_with($text, '/topics')) {
            CommandHandler::handleTopics($this->telegram, $update);
        } elseif (str_starts_with($text, '/create_room')) {
            $args = $this->parseCommandArgs($text);
            CommandHandler::handleCreateRoom($this->telegram, $update, $args);
        } elseif (str_starts_with($text, '/join_room')) {
            $args = $this->parseCommandArgs($text);
            CommandHandler::handleJoinRoom($this->telegram, $update, $args, $this->userStates);
        } elseif (str_starts_with($text, '/stop')) {
            CommandHandler::handleStop($this->telegram, $update);
        } elseif (str_starts_with($text, '/add_topic')) {
            CommandHandler::handleAddTopic($this->telegram, $update, $this->adminId, $this->userStates);
        }
    }

    /**
     * Парсинг аргументов команды
     */
    private function parseCommandArgs(string $text): array
    {
        $args = explode(' ', $text);
        array_shift($args); // Убираем саму команду
        return $args;
    }
}