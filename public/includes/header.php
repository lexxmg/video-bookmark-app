<?php
// public/includes/header.php
require_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Изучение видео' ?></title>
    <!-- ПОДКЛЮЧАЕМ ЛОКАЛЬНЫЙ TAILWIND ИЗ DOCKER -->
    <link rel="stylesheet" href="/includes/tailwind.css">
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col">

    <!-- Общая навигационная панель -->
    <nav class="bg-white shadow-sm p-4 mb-6">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="index.php" class="text-xl font-bold text-blue-600">🎬 Video Study</a>
                <a href="index.php" class="text-gray-600 hover:text-blue-600 text-sm">Главная</a>
            </div>
            <a href="admin.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium transition cursor-pointer">⚙️ Админка</a>
        </div>
    </nav>
