<?php

require_once __DIR__ . '/vendor/autoload.php';

use CardsLite\Bot;
use Dotenv\Dotenv;
use Telegram\Bot\Exceptions\TelegramSDKException;

// Загружаем переменные окружения (только если файл .env существует)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Инициализация подключения к БД через Eloquent
require __DIR__ . '/bootstrap/database.php';

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

// Создаем и запускаем бота
try {
    $bot = new Bot($botToken, $adminId);
    $bot->run();
} catch (TelegramSDKException $e) {
    error_log($e);
}
