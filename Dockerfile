# Use official PHP 8.1 with CLI
FROM php:8.1-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Create data directory for SQLite database
RUN mkdir -p /app/data && chmod 777 /app/data

# Create .env file from example if it doesn't exist
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Run the bot
CMD ["php", "bot.php"]