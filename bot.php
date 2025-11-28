<?php

require_once __DIR__ . '/vendor/autoload.php';

use Telegram\Bot\Api;
use CardsLite\Database;
use CardsLite\Utils;
use CardsLite\UI;
use Dotenv\Dotenv;

// Загружаем переменные окружения (только если файл .env существует)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Проверяем наличие токена
$botToken = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? null);
if (!$botToken) {
    die("❌ Переменная окружения BOT_TOKEN не установлена\n");
}

// ID администратора
$adminId = (int)(getenv('ADMIN_ID') ?: ($_ENV['ADMIN_ID'] ?? 0));
if (!$adminId) {
    die("❌ Переменная окружения ADMIN_ID не установлена. Узнайте свой user_id через @userinfobot\n");
}

// Инициализация бота
$telegram = new Api($botToken);

// Инициализация БД
Database::initDb();

// Хранилище для состояний пользователей (в продакшене использовать БД или Redis)
$userStates = [];

echo "🤖 Бот запущен\n";

// Функция для отправки сообщения с обработкой ошибок
function sendMessage($telegram, $chatId, $text, $replyMarkup = null, $parseMode = 'HTML')
{
    try {
        // Проверка на пустое сообщение
        if (empty(trim($text ?? ''))) {
            error_log("Попытка отправить пустое сообщение в чат {$chatId}");
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

// Обработчик команды /start
function handleStart($telegram, $update): void
{
    $chatId = $update->message->chat->id;

    $welcomeText = "👋 Добро пожаловать в бот для знакомства через мини-игры!\n\n"
        . "🎯 Как это работает:\n"
        . "1️⃣ Выберите тему\n"
        . "2️⃣ Создайте комнату или присоединитесь\n"
        . "3️⃣ Отвечайте на вопросы вместе\n"
        . "4️⃣ Узнавайте друг друга!\n\n"
        . "Начнём? 👇";

    sendMessage($telegram, $chatId, $welcomeText, UI::getMainMenuKeyboard());
}

// Обработчик команды /topics
function handleTopics($telegram, $update): void
{
    $chatId = $update->message->chat->id;
    $topics = Database::getAllTopics();

    if (empty($topics)) {
        sendMessage($telegram, $chatId, "📭 Пока нет доступных тем для игры.");
        return;
    }

    $topicsText = "📚 Доступные темы:\n\n";
    foreach ($topics as $topic) {
        $topicsText .= "{$topic['id']}. {$topic['name']}\n";
    }
    $topicsText .= "\n💡 Используйте /create_room &lt;topic_id&gt; чтобы создать комнату";

    sendMessage($telegram, $chatId, $topicsText);
}

// Обработчик команды /create_room
function handleCreateRoom($telegram, $update, $args): void
{
    $chatId = $update->message->chat->id;
    $userId = $update->message->from->id;

    if (empty($args)) {
        sendMessage($telegram, $chatId,
            "❌ Укажите ID темы.\n"
            . "Использование: /create_room &lt;topic_id&gt;\n"
            . "Посмотрите доступные темы: /topics"
        );
        return;
    }

    $topicId = (int)$args[0];
    $topic = Database::getTopicById($topicId);

    if (!$topic) {
        sendMessage($telegram, $chatId, "❌ Тема с ID {$topicId} не найдена");
        return;
    }

    if (Database::hasActiveRoom($userId)) {
        sendMessage($telegram, $chatId,
            "⚠️ У вас уже есть активная комната.\n"
            . "Завершите текущую игру перед созданием новой."
        );
        return;
    }

    $roomId = Database::createRoom($topicId, $userId);
    $topicName = $topic['name'];

    sendMessage($telegram, $chatId,
        "✅ Комната создана!\n\n"
        . "🎯 Тема: {$topicName}\n"
        . "🔑 ID комнаты: <code>{$roomId}</code>\n\n"
        . "📤 Передайте этот ID собеседнику.\n"
        . "Он должен использовать команду:\n"
        . "/join_room {$roomId}"
    );
}

// Обработчик команды /join_room
function handleJoinRoom($telegram, $update, $args, &$userStates): void
{
    $chatId = $update->message->chat->id;
    $userId = $update->message->from->id;

    if (empty($args)) {
        sendMessage($telegram, $chatId,
            "Введите ID комнаты:\n\n"
            . "(например: <code>123456</code>)",
            UI::getRemoveKeyboard()
        );
        $userStates[$userId] = ['state' => 'waiting_room_id'];
        return;
    }

    $roomId = trim($args[0]);
    joinRoomProcess($telegram, $chatId, $userId, $roomId);
}

// Процесс присоединения к комнате
function joinRoomProcess($telegram, $chatId, $userId, $roomId): void
{
    if (Database::hasActiveRoom($userId)) {
        sendMessage($telegram, $chatId,
            "⚠️ У вас уже есть активная комната.\n"
            . "Завершите текущую игру перед присоединением к новой."
        );
        return;
    }

    $success = Database::joinRoom($roomId, $userId);

    if (!$success) {
        sendMessage($telegram, $chatId,
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

    sendMessage($telegram, $chatId,
        "✅ Вы присоединились к игре!\n\n"
        . "🎯 Тема: <b>{$topicName}</b>\n"
        . "❓ Вопросов: {$totalQuestions}\n\n"
        . "🎮 Игра начинается!"
    );

    // Уведомление первому игроку
    sendMessage($telegram, $player1Id,
        "✅ К вашей комнате присоединился собеседник!\n\n"
        . "🎯 Тема: <b>{$topicName}</b>\n"
        . "❓ Вопросов: {$totalQuestions}\n\n"
        . "🎮 Игра начинается!"
    );

    // Отправляем первый вопрос обоим игрокам
    $questionText = Database::getQuestionByIndex($topicId, 0);
    if ($questionText) {
        $questionMessage = "➡️ Вопрос 1/{$totalQuestions}:\n\n"
            . "<b>{$questionText}</b>\n\n"
            . "💬 Напишите ваш ответ:";

        sendMessage($telegram, $chatId, $questionMessage);
        sendMessage($telegram, $player1Id, $questionMessage);
    } else {
        error_log("Не найден вопрос для темы {$topicId} с индексом 0");
    }
}

// Обработчик команды /stop
function handleStop($telegram, $update): void
{
    $chatId = $update->message->chat->id;
    $userId = $update->message->from->id;

    $room = Database::getUserActiveRoom($userId);

    if (!$room) {
        sendMessage($telegram, $chatId,
            "❌ Вы не находитесь в активной игре.\n"
            . "Создайте комнату или присоединитесь к существующей."
        );
        return;
    }

    $roomId = $room['id'];
    $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);

    Database::closeRoom($roomId);

    sendMessage($telegram, $chatId,
        "👋 Вы покинули игровую комнату.\n\n"
        . "💡 Что дальше?\n"
        . "/topics - посмотреть доступные темы\n"
        . "/create_room &lt;topic_id&gt; - создать новую комнату\n"
        . "/join_room &lt;room_id&gt; - присоединиться к комнате",
        UI::getRemoveKeyboard()
    );

    if ($otherPlayerId) {
        sendMessage($telegram, $otherPlayerId,
            "⚠️ Ваш собеседник покинул игровую комнату.\n\n"
            . "💡 Что дальше?\n"
            . "/topics - посмотреть доступные темы\n"
            . "/create_room &lt;topic_id&gt; - создать новую комнату\n"
            . "/join_room &lt;room_id&gt; - присоединиться к комнате"
        );
    }
}

// Обработчик команды /add_topic (только для админа)
function handleAddTopic($telegram, $update, $adminId, &$userStates): void
{
    $chatId = $update->message->chat->id;
    $userId = $update->message->from->id;

    if (!Utils::isAdmin($userId, $adminId)) {
        sendMessage($telegram, $chatId, "❌ Команда доступна только администратору");
        return;
    }

    sendMessage($telegram, $chatId,
        "📝 Создание новой темы\n\n"
        . "Шаг 1/2: Введите название темы (одной строкой):"
    );

    $userStates[$userId] = ['state' => 'waiting_topic_name'];
}

// Обработчик текстовых сообщений
function handleTextMessage($telegram, $update, &$userStates): void
{
    $chatId = $update->message->chat->id;
    $userId = $update->message->from->id;
    $text = $update->message->text;

    // Обработка состояний диалога
    if (isset($userStates[$userId])) {
        $state = $userStates[$userId]['state'] ?? null;

        if ($state === 'waiting_room_id') {
            $roomId = trim($text);
            unset($userStates[$userId]);
            joinRoomProcess($telegram, $chatId, $userId, $roomId);
            return;
        }

        if ($state === 'waiting_topic_name') {
            $topicName = trim($text);
            if (empty($topicName)) {
                sendMessage($telegram, $chatId, "❌ Название темы не может быть пустым. Попробуйте снова:");
                return;
            }
            $userStates[$userId] = [
                'state' => 'waiting_questions',
                'topic_name' => $topicName
            ];
            sendMessage($telegram, $chatId,
                "✅ Название темы: <b>{$topicName}</b>\n\n"
                . "Шаг 2/2: Отправьте список вопросов в формате:\n"
                . "1.Первый вопрос\n"
                . "2.Второй вопрос\n"
                . "3.Третий вопрос\n\n"
                . "И так далее..."
            );
            return;
        }

        if ($state === 'waiting_questions') {
            $questions = Utils::parseQuestions($text);

            if (empty($questions)) {
                sendMessage($telegram, $chatId,
                    "❌ Не удалось распознать вопросы.\n"
                    . "Убедитесь, что формат правильный:\n"
                    . "1.Текст вопроса\n"
                    . "2.Текст вопроса\n\n"
                    . "Попробуйте снова:"
                );
                return;
            }

            $topicName = $userStates[$userId]['topic_name'];
            $topicId = Database::createTopic($topicName);
            $addedCount = Database::addQuestionsToTopic($topicId, $questions);

            $preview = array_slice($questions, 0, 5);
            $previewText = implode("\n", array_map(fn($i, $q) => ($i+1) . ". $q", array_keys($preview), $preview));
            $more = count($questions) > 5 ? "\n... и ещё " . (count($questions) - 5) : "";

            sendMessage($telegram, $chatId,
                "✅ Тема успешно создана!\n\n"
                . "📌 Название: <b>{$topicName}</b>\n"
                . "🔢 ID темы: <code>{$topicId}</code>\n"
                . "❓ Количество вопросов: {$addedCount}\n\n"
                . "Вопросы:\n{$previewText}{$more}"
            );

            unset($userStates[$userId]);
            return;
        }
    }

    // Обработка кнопок главного меню
    if ($text === "📚 Выбрать тему") {
        $topics = Database::getAllTopics();
        if (empty($topics)) {
            sendMessage($telegram, $chatId, "📭 Пока нет доступных тем для игры.");
            return;
        }
        sendMessage($telegram, $chatId, "📚 Выберите тему:", UI::getTopicSelectionKeyboard($topics));
        return;
    }

    if ($text === "🔗 Присоединиться") {
        sendMessage($telegram, $chatId,
            "Введите ID комнаты:\n\n"
            . "(например: <code>123456</code>)"
        );
        $userStates[$userId] = ['state' => 'waiting_room_id'];
        return;
    }

    // Обработка игровых команд
    if ($text === "▶️ Далее") {
        handleNextButton($telegram, $chatId, $userId);
        return;
    }

    if ($text === "❌ Выход") {
        $room = Database::getUserAnyRoom($userId);
        if ($room) {
            $roomId = $room['id'];
            $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);
            Database::deleteRoom($roomId);

            sendMessage($telegram, $chatId,
                "👋 Вы вышли из игры.\n\n/start - вернуться в меню",
                UI::getRemoveKeyboard()
            );

            if ($otherPlayerId) {
                sendMessage($telegram, $otherPlayerId,
                    "⚠️ Ваш собеседник вышел из игры.\n\n/start - вернуться в меню"
                );
            }
        }
        return;
    }

    // Обработка ответов на вопросы (чат)
    handleChatMessage($telegram, $chatId, $userId, $text);
}

// Обработка кнопки "Далее"
function handleNextButton($telegram, $chatId, $userId): void
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
        sendMessage($telegram, $chatId, "⚠️ Сначала ответьте на вопрос перед тем как нажимать [▶️ Далее]!");
        return;
    } elseif ($userId == $room['player2_id'] && !$player2Answered) {
        sendMessage($telegram, $chatId, "⚠️ Сначала ответьте на вопрос перед тем как нажимать [▶️ Далее]!");
        return;
    }

    Database::setPlayerReady($roomId, $userId, true);
    $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);

    if (Database::checkBothReady($roomId)) {
        $hasNext = Database::moveToNextQuestion($roomId);

        if ($hasNext) {
            $nextQuestionIndex = $currentQuestionIndex + 1;
            Database::resetChatForQuestion($roomId, $nextQuestionIndex);

            $totalQuestions = Database::getTotalQuestionsCount($topicId);
            $nextQuestionText = Database::getQuestionByIndex($topicId, $nextQuestionIndex);

            $questionMessage = "➡️ Переходим к следующему вопросу!\n\n"
                . "❓ Вопрос " . ($nextQuestionIndex + 1) . "/{$totalQuestions}:\n\n"
                . "<b>{$nextQuestionText}</b>\n\n"
                . "💬 Напишите ваш ответ:";

            sendMessage($telegram, $chatId, $questionMessage);
            if ($otherPlayerId) {
                sendMessage($telegram, $otherPlayerId, $questionMessage);
            }
        } else {
            $topic = Database::getTopicById($topicId);
            $finishMsg = UI::formatFinishMessage($topic['name']);
            Database::deleteRoom($roomId);

            sendMessage($telegram, $chatId, $finishMsg, UI::getRemoveKeyboard());
            if ($otherPlayerId) {
                sendMessage($telegram, $otherPlayerId, $finishMsg);
            }
        }
    } else {
        sendMessage($telegram, $chatId,
            "✅ Вы готовы к следующему вопросу!\n"
            . "⏳ Ожидаем готовности собеседника...",
            UI::getRemoveKeyboard()
        );

        if ($otherPlayerId) {
            sendMessage($telegram, $otherPlayerId, "💬 Собеседник готов к следующему вопросу!");
        }
    }
}

// Обработка сообщений чата (ответы на вопросы)
function handleChatMessage($telegram, $chatId, $userId, $messageText): void
{
    $room = Database::getUserAnyRoom($userId);
    if (!$room) {
        return;
    }

    $roomId = $room['id'];
    $currentQuestionIndex = $room['current_question_index'];

    Database::saveChatMessage($roomId, $userId, $currentQuestionIndex, $messageText);
    Database::setPlayerAnswered($roomId, $userId, true);

    $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);
    if (!$otherPlayerId) {
        return;
    }

    if (Database::isChatRevealed($roomId)) {
        // Чат раскрыт - просто копируем сообщение
        sendMessage($telegram, $otherPlayerId, $messageText);
        return;
    }

    // Чат еще не раскрыт
    [$player1First, $player2First] = Database::checkFirstAnsweredStatus($roomId);

    if ($userId == $room['player1_id'] && !$player1First) {
        Database::setPlayerFirstAnswered($roomId, $userId, true);
        $player1First = true;
    } elseif ($userId == $room['player2_id'] && !$player2First) {
        Database::setPlayerFirstAnswered($roomId, $userId, true);
        $player2First = true;
    }

    sendMessage($telegram, $chatId, "✅ Сообщение отправлено!", UI::getRemoveKeyboard());

    if ($player1First && $player2First) {
        // Оба ответили - раскрываем чат
        $chatMessages = Database::getChatMessages($roomId, $currentQuestionIndex);
        $chatHistory = UI::formatChatHistory($chatMessages, $room['player1_id']);

        sendMessage($telegram, $chatId, $chatHistory);
        sendMessage($telegram, $otherPlayerId, $chatHistory);

        Database::setChatRevealed($roomId);

        sendMessage($telegram, $chatId,
            "💬 Теперь вы можете свободно переписываться. Нажмите [▶️ Далее] когда будете готовы к следующему вопросу:",
            UI::getGameNextKeyboard()
        );
        sendMessage($telegram, $otherPlayerId,
            "💬 Теперь вы можете свободно переписываться. Нажмите [▶️ Далее] когда будете готовы к следующему вопросу:",
            UI::getGameNextKeyboard()
        );
    } else {
        sendMessage($telegram, $otherPlayerId,
            "⏳ Собеседник уже ответил на вопрос!\n"
            . "Его ответ откроется после вашего ответа."
        );
    }
}

// Обработка голосовых сообщений
function handleVoiceMessage($telegram, $update): void
{
    $message = $update->message;
    $chatId = $message->chat->id;
    $userId = $message->from->id;
    $voice = $message->voice;

    $room = Database::getUserAnyRoom($userId);
    if (!$room) {
        return;
    }

    $roomId = $room['id'];
    $currentQuestionIndex = $room['current_question_index'];
    $voiceFileId = $voice->fileId;

    Database::saveChatMessage($roomId, $userId, $currentQuestionIndex, null, $voiceFileId, null, 'voice');
    Database::setPlayerAnswered($roomId, $userId, true);

    $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);
    if (!$otherPlayerId) {
        return;
    }

    if (Database::isChatRevealed($roomId)) {
        // Чат раскрыт - пересылаем голосовое сообщение
        $telegram->sendVoice([
            'chat_id' => $otherPlayerId,
            'voice' => $voiceFileId
        ]);
        return;
    }

    // Чат еще не раскрыт
    [$player1First, $player2First] = Database::checkFirstAnsweredStatus($roomId);

    if ($userId == $room['player1_id'] && !$player1First) {
        Database::setPlayerFirstAnswered($roomId, $userId, true);
        $player1First = true;
    } elseif ($userId == $room['player2_id'] && !$player2First) {
        Database::setPlayerFirstAnswered($roomId, $userId, true);
        $player2First = true;
    }

    sendMessage($telegram, $chatId, "✅ Голосовое сообщение отправлено!", UI::getRemoveKeyboard());

    if ($player1First && $player2First) {
        // Оба ответили - раскрываем чат
        $chatMessages = Database::getChatMessages($roomId, $currentQuestionIndex);

        // Отправляем историю и медиафайлы обоим игрокам
        foreach ($chatMessages as $msg) {
            $msgType = $msg['message_type'] ?? 'text';

            if ($msgType === 'voice' && $msg['voice_file_id']) {
                $telegram->sendVoice([
                    'chat_id' => $chatId,
                    'voice' => $msg['voice_file_id']
                ]);
                $telegram->sendVoice([
                    'chat_id' => $otherPlayerId,
                    'voice' => $msg['voice_file_id']
                ]);
            } elseif ($msgType === 'video_note' && $msg['video_note_file_id']) {
                $telegram->sendVideoNote([
                    'chat_id' => $chatId,
                    'video_note' => $msg['video_note_file_id']
                ]);
                $telegram->sendVideoNote([
                    'chat_id' => $otherPlayerId,
                    'video_note' => $msg['video_note_file_id']
                ]);
            }
        }

        $chatHistory = UI::formatChatHistory($chatMessages, $room['player1_id']);
        sendMessage($telegram, $chatId, $chatHistory);
        sendMessage($telegram, $otherPlayerId, $chatHistory);

        Database::setChatRevealed($roomId);

        sendMessage($telegram, $chatId,
            "💬 Теперь вы можете свободно переписываться. Нажмите [▶️ Далее] когда будете готовы к следующему вопросу:",
            UI::getGameNextKeyboard()
        );
        sendMessage($telegram, $otherPlayerId,
            "💬 Теперь вы можете свободно переписываться. Нажмите [▶️ Далее] когда будете готовы к следующему вопросу:",
            UI::getGameNextKeyboard()
        );
    } else {
        sendMessage($telegram, $otherPlayerId,
            "⏳ Собеседник уже ответил на вопрос!\n"
            . "Его ответ откроется после вашего ответа."
        );
    }
}

// Обработка видеосообщений
function handleVideoMessage($telegram, $update): void
{
    $message = $update->message;
    $chatId = $message->chat->id;
    $userId = $message->from->id;
    $videoNote = $message->videoNote;

    $room = Database::getUserAnyRoom($userId);
    if (!$room) {
        return;
    }

    $roomId = $room['id'];
    $currentQuestionIndex = $room['current_question_index'];
    $videoNoteFileId = $videoNote->fileId;

    Database::saveChatMessage($roomId, $userId, $currentQuestionIndex, null, null, $videoNoteFileId, 'video_note');
    Database::setPlayerAnswered($roomId, $userId, true);

    $otherPlayerId = Database::getOtherPlayerId($roomId, $userId);
    if (!$otherPlayerId) {
        return;
    }

    if (Database::isChatRevealed($roomId)) {
        // Чат раскрыт - пересылаем видеосообщение
        $telegram->sendVideoNote([
            'chat_id' => $otherPlayerId,
            'video_note' => $videoNoteFileId
        ]);
        return;
    }

    // Чат еще не раскрыт
    [$player1First, $player2First] = Database::checkFirstAnsweredStatus($roomId);

    if ($userId == $room['player1_id'] && !$player1First) {
        Database::setPlayerFirstAnswered($roomId, $userId, true);
        $player1First = true;
    } elseif ($userId == $room['player2_id'] && !$player2First) {
        Database::setPlayerFirstAnswered($roomId, $userId, true);
        $player2First = true;
    }

    sendMessage($telegram, $chatId, "✅ Видеосообщение отправлено!", UI::getRemoveKeyboard());

    if ($player1First && $player2First) {
        // Оба ответили - раскрываем чат
        $chatMessages = Database::getChatMessages($roomId, $currentQuestionIndex);

        // Отправляем историю и медиафайлы обоим игрокам
        foreach ($chatMessages as $msg) {
            $msgType = $msg['message_type'] ?? 'text';

            if ($msgType === 'voice' && $msg['voice_file_id']) {
                $telegram->sendVoice([
                    'chat_id' => $chatId,
                    'voice' => $msg['voice_file_id']
                ]);
                $telegram->sendVoice([
                    'chat_id' => $otherPlayerId,
                    'voice' => $msg['voice_file_id']
                ]);
            } elseif ($msgType === 'video_note' && $msg['video_note_file_id']) {
                $telegram->sendVideoNote([
                    'chat_id' => $chatId,
                    'video_note' => $msg['video_note_file_id']
                ]);
                $telegram->sendVideoNote([
                    'chat_id' => $otherPlayerId,
                    'video_note' => $msg['video_note_file_id']
                ]);
            }
        }

        $chatHistory = UI::formatChatHistory($chatMessages, $room['player1_id']);
        sendMessage($telegram, $chatId, $chatHistory);
        sendMessage($telegram, $otherPlayerId, $chatHistory);

        Database::setChatRevealed($roomId);

        sendMessage($telegram, $chatId,
            "💬 Теперь вы можете свободно переписываться. Нажмите [▶️ Далее] когда будете готовы к следующему вопросу:",
            UI::getGameNextKeyboard()
        );
        sendMessage($telegram, $otherPlayerId,
            "💬 Теперь вы можете свободно переписываться. Нажмите [▶️ Далее] когда будете готовы к следующему вопросу:",
            UI::getGameNextKeyboard()
        );
    } else {
        sendMessage($telegram, $otherPlayerId,
            "⏳ Собеседник уже ответил на вопрос!\n"
            . "Его ответ откроется после вашего ответа."
        );
    }
}

// Обработчик callback queries
function handleCallbackQuery($telegram, $update): void
{
    $callbackQuery = $update->callbackQuery;
    $data = $callbackQuery->data;
    $chatId = $callbackQuery->message->chat->id;
    $userId = $callbackQuery->from->id;

    $telegram->answerCallbackQuery(['callback_query_id' => $callbackQuery->id]);

    if (str_starts_with($data, 'select_topic_')) {
        $topicId = (int)str_replace('select_topic_', '', $data);

        if (Database::hasActiveRoom($userId)) {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $callbackQuery->message->messageId,
                'text' => "⚠️ У вас уже есть активная комната.\nЗавершите текущую игру перед созданием новой."
            ]);
            return;
        }

        $topic = Database::getTopicById($topicId);
        if (!$topic) {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $callbackQuery->message->messageId,
                'text' => "❌ Тема не найдена"
            ]);
            return;
        }

        $roomId = Database::createRoom($topicId, $userId);
        $topicName = $topic['name'];

        $telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $callbackQuery->message->messageId,
            'text' => "✅ Комната создана!\n\n"
                . "🎯 Тема: {$topicName}\n"
                . "🔑 ID комнаты: <code>{$roomId}</code>\n\n"
                . "📤 Передайте этот ID собеседнику или используйте команду:\n"
                . "<code>/join_room {$roomId}</code>",
            'parse_mode' => 'HTML'
        ]);
    }
}

// Основной цикл бота (long polling)
$offset = 0;
while (true) {
    try {
        $updates = $telegram->getUpdates(['offset' => $offset, 'timeout' => 30]);

        foreach ($updates as $update) {
            $offset = $update->updateId + 1;

            if ($update->message) {
                $message = $update->message;

                // Проверка на голосовое сообщение
                if ($message->voice) {
                    handleVoiceMessage($telegram, $update);
                    continue;
                }

                // Проверка на видеосообщение
                if ($message->videoNote) {
                    handleVideoMessage($telegram, $update);
                    continue;
                }

                $text = $message->text ?? '';

                if (str_starts_with($text, '/start')) {
                    handleStart($telegram, $update);
                } elseif (str_starts_with($text, '/topics')) {
                    handleTopics($telegram, $update);
                } elseif (str_starts_with($text, '/create_room')) {
                    $args = explode(' ', $text);
                    array_shift($args);
                    handleCreateRoom($telegram, $update, $args);
                } elseif (str_starts_with($text, '/join_room')) {
                    $args = explode(' ', $text);
                    array_shift($args);
                    handleJoinRoom($telegram, $update, $args, $userStates);
                } elseif (str_starts_with($text, '/stop')) {
                    handleStop($telegram, $update);
                } elseif (str_starts_with($text, '/add_topic')) {
                    handleAddTopic($telegram, $update, $adminId, $userStates);
                } else {
                    handleTextMessage($telegram, $update, $userStates);
                }
            } elseif ($update->callbackQuery) {
                handleCallbackQuery($telegram, $update);
            }
        }
    } catch (Exception $e) {
        error_log("Ошибка в основном цикле: " . $e->getMessage());
        sleep(5);
    }
}
