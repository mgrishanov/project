FROM php:8.3-cli

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install zip bcmath

# Установка расширения для работы с Kafka
RUN apt-get install -y librdkafka-dev \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /app

# Генерация автозагрузчика
# RUN composer dump-autoload --optimize

# Установка прав на запуск скриптов
# RUN chmod +x bin/*

# Создание директории для логов
RUN mkdir -p /var/log/wb-parser && chmod -R 777 /var/log/wb-parser

# Создание директории для вывода
RUN mkdir -p /app/output && chmod -R 777 /app/output

# Команда по умолчанию
# CMD ["php", "bin/console", "list"]
