FROM php:8.2-cli

# Установка системных зависимостей (кешируется пока не меняются пакеты)
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Установка PHP расширений (кешируется)
RUN docker-php-ext-install pdo pdo_sqlite pdo_mysql

# Копирование composer из официального образа (кешируется)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Копирование только composer файлов (пересобирается только при изменении зависимостей)
COPY composer.json composer.lock ./
# Увеличиваем таймауты и используем packagist зеркало для России
RUN composer config --global process-timeout 600 && \
    composer config --global repos.packagist composer https://packagist.org && \
    COMPOSER_PROCESS_TIMEOUT=600 composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Копирование исходного кода (изменяется чаще всего)
COPY . .

RUN mkdir -p /app/data && chmod 777 /app/data

# Make migrate script executable
RUN chmod +x /app/migrate

CMD ["php", "bot.php"]
