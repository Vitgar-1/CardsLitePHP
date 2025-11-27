-- Схема базы данных для MySQL
-- Используется для первоначальной настройки продакшен-сервера

-- Создание базы данных (если еще не создана)
-- CREATE DATABASE IF NOT EXISTS cardslite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE cardslite;

-- Таблица тем
CREATE TABLE IF NOT EXISTS topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица вопросов
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    question_text TEXT NOT NULL,
    order_num INT NOT NULL,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    UNIQUE KEY unique_topic_order (topic_id, order_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица комнат
CREATE TABLE IF NOT EXISTS rooms (
    id VARCHAR(10) PRIMARY KEY,
    topic_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'waiting',
    current_question_index INT NOT NULL DEFAULT 0,
    player1_id BIGINT,
    player2_id BIGINT,
    player1_ready BOOLEAN DEFAULT 0,
    player2_ready BOOLEAN DEFAULT 0,
    player1_message_id BIGINT,
    player2_message_id BIGINT,
    player1_answered BOOLEAN DEFAULT 0,
    player2_answered BOOLEAN DEFAULT 0,
    player1_first_answered BOOLEAN DEFAULT 0,
    player2_first_answered BOOLEAN DEFAULT 0,
    chat_revealed BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Индексы для комнат
CREATE INDEX IF NOT EXISTS idx_rooms_player1 ON rooms(player1_id, status);
CREATE INDEX IF NOT EXISTS idx_rooms_player2 ON rooms(player2_id, status);

-- Таблица ответов
CREATE TABLE IF NOT EXISTS answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(10) NOT NULL,
    user_id BIGINT NOT NULL,
    question_index INT NOT NULL,
    answer_text TEXT NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_user_question (room_id, user_id, question_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Индекс для ответов
CREATE INDEX IF NOT EXISTS idx_answers_room ON answers(room_id, question_index);

-- Таблица сообщений чата
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(10) NOT NULL,
    user_id BIGINT NOT NULL,
    question_index INT NOT NULL,
    message_type VARCHAR(20) NOT NULL DEFAULT 'text',
    message_text TEXT,
    voice_file_id VARCHAR(255),
    video_note_file_id VARCHAR(255),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Индекс для сообщений чата
CREATE INDEX IF NOT EXISTS idx_chat_messages_room ON chat_messages(room_id, question_index);