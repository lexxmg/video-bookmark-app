# ./Dockerfile (в корне проекта)
FROM php:8.2-fpm-alpine

# Устанавливаем только системный SQLite
RUN apk add --no-cache sqlite-dev

# Включаем расширение PDO SQLite
RUN docker-php-ext-install pdo pdo_sqlite

WORKDIR /var/www/html
