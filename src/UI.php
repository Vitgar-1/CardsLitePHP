<?php

namespace CardsLite;

use Telegram\Bot\Keyboard\Keyboard;

/**
 * UI утилиты для генерирования клавиатур и форматирования сообщений
 */
class UI
{
    /**
     * Главное меню после /start
     */
    public static function getMainMenuKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->row([
                Keyboard::button(['text' => '📚 Выбрать тему']),
                Keyboard::button(['text' => '🔗 Присоединиться'])
            ])
            ->setResizeKeyboard(true);
    }

    /**
     * Клавиатура с выбором темы
     */
    public static function getTopicSelectionKeyboard(array $topics): Keyboard
    {
        $keyboard = Keyboard::make()->inline();

        foreach ($topics as $topic) {
            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => $topic['name'],
                    'callback_data' => 'select_topic_' . $topic['id']
                ])
            ]);
        }

        return $keyboard;
    }

    /**
     * Удалить меню быстрых команд
     */
    public static function getRemoveKeyboard(): Keyboard
    {
        return Keyboard::remove();
    }

    /**
     * Меню для игры - перейти к следующему вопросу
     */
    public static function getGameNextKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->row([Keyboard::button(['text' => '▶️ Далее'])])
            ->row([Keyboard::button(['text' => '❌ Выход'])])
            ->setResizeKeyboard(true);
    }

    /**
     * Сообщение о завершении игры
     */
    public static function formatFinishMessage(string $topicName): string
    {
        return "🎉 Отлично! Вы прошли все вопросы по теме «{$topicName}»!\n\n"
            . "💡 Хотите продолжить знакомство?";
    }

    /**
     * Форматирует историю чата между двумя игроками
     */
    public static function formatChatHistory(
        array $chatMessages,
        int $player1Id
    ): string {
        if (empty($chatMessages)) {
            return "Нет сообщений в чате";
        }

        $message = "💬 История ответов:\n\n";

        foreach ($chatMessages as $msg) {
            $userId = $msg['user_id'];
            $msgType = $msg['message_type'] ?? 'text';

            $senderName = ($userId == $player1Id) ? "Игрок 1" : "Игрок 2";

            if ($msgType === 'text') {
                $text = $msg['message_text'];
                $message .= "👤 $senderName:\n$text\n\n";
            } elseif ($msgType === 'voice') {
                $message .= "👤 $senderName:\n🎙️ [Голосовое сообщение]";
            }
        }

        return $message;
    }
}
