<?php
// public/api.php

header('Content-Type: application/json');

// Подключаем единый файл базы данных (он сам создаст папки и таблицы, если их нет)
require_once __DIR__ . '/includes/db.php';

// Проверяем, что $pdo успешно подключен из файла db.php
if (!isset($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Ошибка инициализации PDO']);
    exit;
}

// Обработка POST-запросов от фронтенда (JavaScript)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- СОХРАНЕНИЕ МИНИАТЮРЫ НА ДИСК ---
    if ($action === 'save_thumbnail') {
        $video_id = (int)($_POST['id'] ?? 0);
        $imgData = $_POST['image'] ?? '';

        if (empty($imgData) || $video_id === 0) {
            echo json_encode(['success' => false, 'error' => 'Нет данных']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT file_name FROM videos WHERE id = ?");
            $stmt->execute([$video_id]);
            $video = $stmt->fetch();

            if ($video) {
                $imgData = str_replace('data:image/jpeg;base64,', '', $imgData);
                $imgData = str_replace(' ', '+', $imgData);
                $binaryData = base64_decode($imgData);

                // НОВЫЙ ПУТЬ: Сохраняем в отдельный каталог thumbnails/
                $thumbDir = __DIR__ . '/../storage/thumbnails/';
                if (!file_exists($thumbDir)) {
                    mkdir($thumbDir, 0777, true);
                }

                $fileNameNoExt = pathinfo($video['file_name'], PATHINFO_FILENAME);
                $imgPath = $thumbDir . $fileNameNoExt . '.jpg';

                file_put_contents($imgPath, $binaryData);

                $stmt = $pdo->prepare("UPDATE videos SET has_thumbnail = 1 WHERE id = ?");
                $stmt->execute([$video_id]);

                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Видео не найдено']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }


    // --- ДОБАВЛЕНИЕ МЕТКИ ---
    if ($action === 'add_bookmark') {
        $video_id = (int)($_POST['video_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $timestamp = (float)($_POST['timestamp'] ?? 0);

        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Название метки не может быть пустым']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO bookmarks (video_id, title, timestamp) VALUES (?, ?, ?)");
            $stmt->execute([$video_id, $title, $timestamp]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Не удалось сохранить: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- РЕДАКТИРОВАНИЕ МЕТКИ ---
    if ($action === 'edit_bookmark') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');

        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Название не может быть пустым']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE bookmarks SET title = ? WHERE id = ?");
            $stmt->execute([$title, $id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Не удалось обновить: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- УДАЛЕНИЕ МЕТКИ ---
    if ($action === 'delete_bookmark') {
        $id = (int)($_POST['id'] ?? 0);

        try {
            $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Не удалось удалить: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- РЕДАКТИРОВАНИЕ НАЗВАНИЯ ВИДЕО ---
    if ($action === 'edit_video_title') {
        $video_id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');

        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Название видео не может быть пустым']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE videos SET title = ? WHERE id = ?");
            $stmt->execute([$title, $video_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }


    // --- СОХРАНЕНИЕ ГРОМКОСТИ ---
    if ($action === 'save_volume') {
        $video_id = (int)($_POST['video_id'] ?? 0);
        $volume = (float)($_POST['volume'] ?? 1.0);

        try {
            $stmt = $pdo->prepare("UPDATE videos SET volume = ? WHERE id = ?");
            $stmt->execute([$volume, $video_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Неверный запрос API']);
exit;
