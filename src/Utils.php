<?php

namespace CardsLite;

/**
 * Вспомогательные функции
 */
class Utils
{
    /**
     * Парсинг вопросов из текста формата:
     * 1. Первый вопрос
     * 2. Второй вопрос
     * 3. Третий вопрос
     *
     * Возвращает массив строк с текстами вопросов (без номеров)
     */
    public static function parseQuestions(string $text): array
    {
        $questions = [];

        // Паттерн: номер + точка + текст до следующего номера или конца строки
        $pattern = '/\d+\.\s*(.+?)(?=\n\s*\d+\.|\Z)/s';

        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $match) {
                $question = trim($match);
                if (!empty($question)) {
                    $questions[] = $question;
                }
            }
        }

        return $questions;
    }

    /**
     * Проверка, является ли пользователь администратором
     */
    public static function isAdmin(int $userId, int $adminId): bool
    {
        return $userId === $adminId;
    }
}
