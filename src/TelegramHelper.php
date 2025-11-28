<?php

namespace CardsLite;

use Exception;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message;

/**
 * Хелпер для работы с Telegram API
 */
class TelegramHelper
{
    /**
     * Отправка сообщения с обработкой ошибок
     */
    public static function sendMessage(
        Api $telegram,
        int $chatId,
        string $text,
        $replyMarkup = null,
        string $parseMode = 'HTML'
    ): ?Message
    {
        try {
            if (empty(trim($text))) {
                error_log("Попытка отправить пустое сообщение в чат $chatId");
                return null;
            }

            $params = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode
            ];

            if ($replyMarkup !== null) {
                $params['reply_markup'] = $replyMarkup;
            }

            return $telegram->sendMessage($params);
        } catch (Exception $e) {
            error_log("Ошибка отправки сообщения: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Отправка голосового сообщения
     */
    public static function sendVoice(Api $telegram, int $chatId, string $fileId): void
    {
        try {
            $telegram->sendVoice(['chat_id' => $chatId, 'voice' => $fileId]);
        } catch (Exception $e) {
            error_log("Ошибка отправки голосового: " . $e->getMessage());
        }
    }

    /**
     * Отправка видеосообщения
     */
    public static function sendVideoNote(Api $telegram, int $chatId, string $fileId): void
    {
        try {
            $telegram->sendVideoNote(['chat_id' => $chatId, 'video_note' => $fileId]);
        } catch (Exception $e) {
            error_log("Ошибка отправки видео: " . $e->getMessage());
        }
    }

    /**
     * Редактирование текста сообщения
     */
    public static function editMessageText(
        Api $telegram,
        int $chatId,
        int $messageId,
        string $text,
        string $parseMode = 'HTML'
    ): void {
        try {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => $parseMode
            ]);
        } catch (Exception $e) {
            error_log("Ошибка редактирования сообщения: " . $e->getMessage());
        }
    }
}