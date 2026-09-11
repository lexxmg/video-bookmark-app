FROM php:8.2-fpm-alpine

# 1. Устанавливаем системные пакеты для SQLite, а также Node.js и NPM
RUN apk add --no-cache sqlite-dev nodejs npm

# 2. Включаем расширение PDO SQLite
RUN docker-php-ext-install pdo pdo_sqlite

WORKDIR /var/www/html

# 3. Копируем файлы проекта внутрь контейнера для сборки
COPY . /var/www/html

# 4. Скачиваем официальный пакет Tailwind v4 локально внутри контейнера
RUN npm install @tailwindcss/cli

# 5. Запускаем компиляцию стилей через npx.
# Сборщик просканирует весь проект по правилу @source и сохранит готовый CSS в /tmp/
RUN npx tailwindcss \
    -i /var/www/html/public/includes/input.css \
    -o /tmp/tailwind.css --minify
