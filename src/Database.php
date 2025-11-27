<?php

namespace CardsLite;

use PDO;
use PDOException;

/**
 * Модуль для работы с SQLite базой данных
 */
class Database
{
    private static ?PDO $connection = null;

    /**
     * Получить соединение с БД
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                $dbType = getenv('DB_TYPE') ?: ($_ENV['DB_TYPE'] ?? 'sqlite');

                if ($dbType === 'mysql') {
                    // Подключение к MySQL
                    $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
                    $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
                    $dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'cardslite');
                    $user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
                    $pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

                    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
                    self::$connection = new PDO($dsn, $user, $pass);
                } else {
                    // Подключение к SQLite (по умолчанию)
                    $dbFile = getenv('DB_SQLITE_FILE') ?: ($_ENV['DB_SQLITE_FILE'] ?? 'cardslite.db');
                    self::$connection = new PDO('sqlite:' . $dbFile);
                }

                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$connection;
    }

    /**
     * Инициализация БД: создание всех таблиц
     */
    public static function initDb(): void
    {
        $conn = self::getConnection();

        // Таблица тем
        $conn->exec("
            CREATE TABLE IF NOT EXISTS topics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Таблица вопросов
        $conn->exec("
            CREATE TABLE IF NOT EXISTS questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                topic_id INTEGER NOT NULL,
                question_text TEXT NOT NULL,
                order_num INTEGER NOT NULL,
                FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
                UNIQUE(topic_id, order_num)
            )
        ");

        // Таблица комнат
        $conn->exec("
            CREATE TABLE IF NOT EXISTS rooms (
                id TEXT PRIMARY KEY,
                topic_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'waiting',
                current_question_index INTEGER NOT NULL DEFAULT 0,
                player1_id INTEGER,
                player2_id INTEGER,
                player1_ready BOOLEAN DEFAULT 0,
                player2_ready BOOLEAN DEFAULT 0,
                player1_message_id INTEGER,
                player2_message_id INTEGER,
                player1_answered BOOLEAN DEFAULT 0,
                player2_answered BOOLEAN DEFAULT 0,
                player1_first_answered BOOLEAN DEFAULT 0,
                player2_first_answered BOOLEAN DEFAULT 0,
                chat_revealed BOOLEAN DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (topic_id) REFERENCES topics(id)
            )
        ");

        $conn->exec("
            CREATE INDEX IF NOT EXISTS idx_rooms_player1 ON rooms(player1_id, status)
        ");

        $conn->exec("
            CREATE INDEX IF NOT EXISTS idx_rooms_player2 ON rooms(player2_id, status)
        ");

        // Таблица ответов
        $conn->exec("
            CREATE TABLE IF NOT EXISTS answers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                question_index INTEGER NOT NULL,
                answer_text TEXT NOT NULL,
                answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
                UNIQUE(room_id, user_id, question_index)
            )
        ");

        $conn->exec("
            CREATE INDEX IF NOT EXISTS idx_answers_room ON answers(room_id, question_index)
        ");

        // Таблица сообщений чата
        $conn->exec("
            CREATE TABLE IF NOT EXISTS chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                question_index INTEGER NOT NULL,
                message_type TEXT NOT NULL DEFAULT 'text',
                message_text TEXT,
                voice_file_id TEXT,
                video_note_file_id TEXT,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
            )
        ");

        $conn->exec("
            CREATE INDEX IF NOT EXISTS idx_chat_messages_room ON chat_messages(room_id, question_index)
        ");

        echo "✓ База данных инициализирована\n";
    }

    /**
     * Получить список всех тем
     */
    public static function getAllTopics(): array
    {
        $conn = self::getConnection();
        $stmt = $conn->query("SELECT id, name FROM topics ORDER BY id");
        return $stmt->fetchAll();
    }

    /**
     * Получить тему по id
     */
    public static function getTopicById(int $topicId): ?array
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT id, name FROM topics WHERE id = ?");
        $stmt->execute([$topicId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Генерация уникального room_id (6 цифр)
     */
    public static function generateRoomId(): string
    {
        return str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Проверить, есть ли у пользователя активная комната
     */
    public static function hasActiveRoom(int $userId): bool
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM rooms
            WHERE (player1_id = ? OR player2_id = ?)
            AND status IN ('waiting', 'active')
        ");
        $stmt->execute([$userId, $userId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    /**
     * Создать новую комнату
     */
    public static function createRoom(int $topicId, int $creatorId): string
    {
        $roomId = self::generateRoomId();
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            INSERT INTO rooms (id, topic_id, player1_id, status)
            VALUES (?, ?, ?, 'waiting')
        ");
        $stmt->execute([$roomId, $topicId, $creatorId]);
        return $roomId;
    }

    /**
     * Получить информацию о комнате
     */
    public static function getRoom(string $roomId): ?array
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Подключить второго игрока к комнате
     */
    public static function joinRoom(string $roomId, int $userId): bool
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT player1_id, player2_id, status FROM rooms WHERE id = ?
        ");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        // Нельзя присоединиться к своей же комнате
        if ($row['player1_id'] == $userId) {
            return false;
        }

        // Комната должна быть в статусе waiting и без второго игрока
        if ($row['status'] !== 'waiting' || $row['player2_id'] !== null) {
            return false;
        }

        // Подключаем второго игрока и меняем статус на active
        $stmt = $conn->prepare("
            UPDATE rooms SET player2_id = ?, status = 'active'
            WHERE id = ?
        ");
        $stmt->execute([$userId, $roomId]);
        return true;
    }

    /**
     * Создать новую тему
     */
    public static function createTopic(string $topicName): int
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("INSERT INTO topics (name) VALUES (?)");
        $stmt->execute([$topicName]);
        return (int)$conn->lastInsertId();
    }

    /**
     * Добавить вопросы к теме
     */
    public static function addQuestionsToTopic(int $topicId, array $questions): int
    {
        $conn = self::getConnection();
        $addedCount = 0;

        foreach ($questions as $orderNum => $questionText) {
            $stmt = $conn->prepare("
                INSERT INTO questions (topic_id, question_text, order_num)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$topicId, trim($questionText), $orderNum]);
            $addedCount++;
        }

        return $addedCount;
    }

    /**
     * Получить активную комнату пользователя
     */
    public static function getUserActiveRoom(int $userId): ?array
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT * FROM rooms
            WHERE (player1_id = ? OR player2_id = ?)
            AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$userId, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Получить любую комнату пользователя (активную или ждущую)
     */
    public static function getUserAnyRoom(int $userId): ?array
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT * FROM rooms
            WHERE (player1_id = ? OR player2_id = ?)
            AND status IN ('waiting', 'active')
            LIMIT 1
        ");
        $stmt->execute([$userId, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Получить текст вопроса по индексу
     */
    public static function getQuestionByIndex(int $topicId, int $questionIndex): ?string
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT question_text FROM questions
            WHERE topic_id = ? AND order_num = ?
        ");
        $stmt->execute([$topicId, $questionIndex]);
        $result = $stmt->fetch();
        return $result ? $result['question_text'] : null;
    }

    /**
     * Получить общее количество вопросов в теме
     */
    public static function getTotalQuestionsCount(int $topicId): int
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM questions WHERE topic_id = ?
        ");
        $stmt->execute([$topicId]);
        $result = $stmt->fetch();
        return (int)$result['count'];
    }

    /**
     * Сохранить ответ пользователя на вопрос
     */
    public static function saveAnswer(string $roomId, int $userId, int $questionIndex, string $answerText): void
    {
        $conn = self::getConnection();
        $dbType = getenv('DB_TYPE') ?: ($_ENV['DB_TYPE'] ?? 'sqlite');

        if ($dbType === 'mysql') {
            // MySQL: используем REPLACE INTO
            $stmt = $conn->prepare("
                REPLACE INTO answers (room_id, user_id, question_index, answer_text)
                VALUES (?, ?, ?, ?)
            ");
        } else {
            // SQLite: используем INSERT OR REPLACE
            $stmt = $conn->prepare("
                INSERT OR REPLACE INTO answers (room_id, user_id, question_index, answer_text)
                VALUES (?, ?, ?, ?)
            ");
        }

        $stmt->execute([$roomId, $userId, $questionIndex, $answerText]);
    }

    /**
     * Получить ответ пользователя на вопрос
     */
    public static function getAnswer(string $roomId, int $userId, int $questionIndex): ?string
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT answer_text FROM answers
            WHERE room_id = ? AND user_id = ? AND question_index = ?
        ");
        $stmt->execute([$roomId, $userId, $questionIndex]);
        $result = $stmt->fetch();
        return $result ? $result['answer_text'] : null;
    }

    /**
     * Проверить, ответили ли оба игрока на текущий вопрос
     */
    public static function checkBothAnswered(string $roomId, int $questionIndex): bool
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT player1_id, player2_id FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM answers
            WHERE room_id = ? AND question_index = ?
            AND user_id IN (?, ?)
        ");
        $stmt->execute([$roomId, $questionIndex, $row['player1_id'], $row['player2_id']]);
        $result = $stmt->fetch();
        return $result['count'] == 2;
    }

    /**
     * Установить флаг готовности игрока к следующему вопросу
     */
    public static function setPlayerReady(string $roomId, int $userId, bool $ready = true): void
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT player1_id, player2_id FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        $readyValue = $ready ? 1 : 0;

        if ($userId == $row['player1_id']) {
            $stmt = $conn->prepare("UPDATE rooms SET player1_ready = ? WHERE id = ?");
            $stmt->execute([$readyValue, $roomId]);
        } elseif ($userId == $row['player2_id']) {
            $stmt = $conn->prepare("UPDATE rooms SET player2_ready = ? WHERE id = ?");
            $stmt->execute([$readyValue, $roomId]);
        }
    }

    /**
     * Проверить, готовы ли оба игрока к следующему вопросу
     */
    public static function checkBothReady(string $roomId): bool
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT player1_ready, player2_ready FROM rooms WHERE id = ?
        ");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        return $row['player1_ready'] == 1 && $row['player2_ready'] == 1;
    }

    /**
     * Перейти к следующему вопросу
     */
    public static function moveToNextQuestion(string $roomId): bool
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT current_question_index, topic_id FROM rooms WHERE id = ?
        ");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        $nextIndex = $row['current_question_index'] + 1;

        // Проверяем, есть ли ещё вопросы
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM questions WHERE topic_id = ? AND order_num = ?
        ");
        $stmt->execute([$row['topic_id'], $nextIndex]);
        $result = $stmt->fetch();
        $hasNext = $result['count'] > 0;

        if ($hasNext) {
            // Переходим к следующему вопросу и сбрасываем флаги
            $stmt = $conn->prepare("
                UPDATE rooms
                SET current_question_index = ?,
                    player1_ready = 0,
                    player2_ready = 0,
                    player1_answered = 0,
                    player2_answered = 0
                WHERE id = ?
            ");
            $stmt->execute([$nextIndex, $roomId]);
            return true;
        } else {
            // Вопросы закончились
            $stmt = $conn->prepare("
                UPDATE rooms SET status = 'finished' WHERE id = ?
            ");
            $stmt->execute([$roomId]);
            return false;
        }
    }

    /**
     * Получить ID собеседника в комнате
     */
    public static function getOtherPlayerId(string $roomId, int $userId): ?int
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT player1_id, player2_id FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $userId == $row['player1_id'] ? $row['player2_id'] : $row['player1_id'];
    }

    /**
     * Закрыть комнату
     */
    public static function closeRoom(string $roomId): bool
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            UPDATE rooms SET status = 'finished' WHERE id = ?
        ");
        $stmt->execute([$roomId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Удалить комнату и все связанные ответы
     */
    public static function deleteRoom(string $roomId): bool
    {
        $conn = self::getConnection();

        $stmt = $conn->prepare("DELETE FROM answers WHERE room_id = ?");
        $stmt->execute([$roomId]);

        $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Сохранить ID сообщения
     */
    public static function saveMessageId(string $roomId, int $userId, int $messageId): void
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT player1_id, player2_id FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        if ($userId == $row['player1_id']) {
            $stmt = $conn->prepare("UPDATE rooms SET player1_message_id = ? WHERE id = ?");
            $stmt->execute([$messageId, $roomId]);
        } elseif ($userId == $row['player2_id']) {
            $stmt = $conn->prepare("UPDATE rooms SET player2_message_id = ? WHERE id = ?");
            $stmt->execute([$messageId, $roomId]);
        }
    }

    /**
     * Получить ID сообщения
     */
    public static function getMessageId(string $roomId, int $userId): ?int
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT player1_id, player1_message_id, player2_message_id FROM rooms WHERE id = ?
        ");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if ($userId == $row['player1_id']) {
            return $row['player1_message_id'];
        } else {
            return $row['player2_message_id'];
        }
    }

    /**
     * Установить флаг 'ответил' для игрока
     */
    public static function setPlayerAnswered(string $roomId, int $userId, bool $answered = true): void
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT player1_id, player2_id FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        $answeredValue = $answered ? 1 : 0;

        if ($userId == $row['player1_id']) {
            $stmt = $conn->prepare("UPDATE rooms SET player1_answered = ? WHERE id = ?");
            $stmt->execute([$answeredValue, $roomId]);
        } elseif ($userId == $row['player2_id']) {
            $stmt = $conn->prepare("UPDATE rooms SET player2_answered = ? WHERE id = ?");
            $stmt->execute([$answeredValue, $roomId]);
        }
    }

    /**
     * Получить статус ответов обоих игроков
     */
    public static function checkAnswerStatus(string $roomId): array
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT player1_answered, player2_answered FROM rooms WHERE id = ?
        ");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return [false, false];
        }

        return [$row['player1_answered'] == 1, $row['player2_answered'] == 1];
    }

    /**
     * Сбросить флаги ответов
     */
    public static function resetAnswerStatus(string $roomId): void
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            UPDATE rooms SET player1_answered = 0, player2_answered = 0 WHERE id = ?
        ");
        $stmt->execute([$roomId]);
    }

    /**
     * Сохранить сообщение в чат
     */
    public static function saveChatMessage(
        string $roomId,
        int $userId,
        int $questionIndex,
        ?string $messageText = null,
        ?string $voiceFileId = null,
        ?string $videoNoteFileId = null,
        string $messageType = 'text'
    ): void {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            INSERT INTO chat_messages (room_id, user_id, question_index, message_type, message_text, voice_file_id, video_note_file_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$roomId, $userId, $questionIndex, $messageType, $messageText, $voiceFileId, $videoNoteFileId]);
    }

    /**
     * Получить все сообщения чата для конкретного вопроса
     */
    public static function getChatMessages(string $roomId, int $questionIndex): array
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT user_id, message_type, message_text, voice_file_id, video_note_file_id, sent_at FROM chat_messages
            WHERE room_id = ? AND question_index = ?
            ORDER BY sent_at ASC
        ");
        $stmt->execute([$roomId, $questionIndex]);
        return $stmt->fetchAll();
    }

    /**
     * Отметить что игрок впервые ответил
     */
    public static function setPlayerFirstAnswered(string $roomId, int $userId, bool $firstAnswered = true): void
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT player1_id, player2_id FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        $value = $firstAnswered ? 1 : 0;

        if ($userId == $row['player1_id']) {
            $stmt = $conn->prepare("UPDATE rooms SET player1_first_answered = ? WHERE id = ?");
            $stmt->execute([$value, $roomId]);
        } elseif ($userId == $row['player2_id']) {
            $stmt = $conn->prepare("UPDATE rooms SET player2_first_answered = ? WHERE id = ?");
            $stmt->execute([$value, $roomId]);
        }
    }

    /**
     * Получить статус первого ответа обоих игроков
     */
    public static function checkFirstAnsweredStatus(string $roomId): array
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
            SELECT player1_first_answered, player2_first_answered FROM rooms WHERE id = ?
        ");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return [false, false];
        }

        return [$row['player1_first_answered'] == 1, $row['player2_first_answered'] == 1];
    }

    /**
     * Очистить чат и флаги при переходе к новому вопросу
     */
    public static function resetChatForQuestion(string $roomId, int $questionIndex): void
    {
        $conn = self::getConnection();

        $stmt = $conn->prepare("
            DELETE FROM chat_messages WHERE room_id = ? AND question_index = ?
        ");
        $stmt->execute([$roomId, $questionIndex]);

        $stmt = $conn->prepare("
            UPDATE rooms SET player1_first_answered = 0, player2_first_answered = 0,
                            player1_answered = 0, player2_answered = 0, chat_revealed = 0
            WHERE id = ?
        ");
        $stmt->execute([$roomId]);
    }

    /**
     * Отметить что чат раскрыт
     */
    public static function setChatRevealed(string $roomId): void
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("UPDATE rooms SET chat_revealed = 1 WHERE id = ?");
        $stmt->execute([$roomId]);
    }

    /**
     * Проверить раскрыт ли чат
     */
    public static function isChatRevealed(string $roomId): bool
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT chat_revealed FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        return $row['chat_revealed'] == 1;
    }
}
