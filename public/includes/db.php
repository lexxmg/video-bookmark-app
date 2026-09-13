<?php
// public/includes/db.php
$dbPath = __DIR__ . '/../../database/database.sqlite';

if (!file_exists(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0777, true);
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Включаем поддержку каскадного удаления меток
    $pdo->exec('PRAGMA foreign_keys = ON;');
    
    // Переводим SQLite в режим WAL для высокой скорости работы
    $pdo->exec('PRAGMA journal_mode = WAL;');

    // Создание таблиц с нуля (чистая, монолитная структура)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            file_name TEXT NOT NULL UNIQUE,
            volume REAL DEFAULT 1.0,
            has_thumbnail INTEGER DEFAULT 0,
            views_count INTEGER DEFAULT 0,
            last_position REAL DEFAULT 0.0,
            sort_order INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS bookmarks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            video_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            timestamp REAL NOT NULL,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_bookmarks_video_timestamp ON bookmarks(video_id, timestamp);

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );

        INSERT OR IGNORE INTO settings (key, value) VALUES ('sort_by', 'id_desc');
    ");

} catch (PDOException $e) {
    die('Ошибка базы данных: ' . $e->getMessage());
}

// Глобальная функция защиты от XSS
function h($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
