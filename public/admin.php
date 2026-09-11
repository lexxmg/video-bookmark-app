<?php
// public/admin.php
$pageTitle = "Панель администратора";
include __DIR__ . '/includes/header.php'; // Подключаем общую шапку со стилями и $pdo

// Путь к папке с видео внутри Docker-контейнера
$videosDir = __DIR__ . '/../storage/videos/';
$message = '';

if (isset($_POST['sync'])) {
    $diskFiles = [];
    
    // Проверяем, существует ли физическая папка в Docker
    if (is_dir($videosDir)) {
        $files = scandir($videosDir);
        foreach ($files as $file) {
            // Отбираем только файлы с расширением .mp4
            if ($file !== '.' && $file !== '..' && $file !== '.gitkeep' && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'mp4') {
                $diskFiles[] = $file;
            }
        }
    }

    // 1. Добавляем новые файлы, которых еще нет в базе данных
    $addedCount = 0;
    foreach ($diskFiles as $file) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE file_name = ?");
        $stmt->execute([$file]);
        if ($stmt->fetchColumn() == 0) {
            // Названием плитки делаем имя файла (без .mp4)
            $title = pathinfo($file, PATHINFO_FILENAME);
            
            $stmt = $pdo->prepare("INSERT INTO videos (title, file_name) VALUES (?, ?)");
            $stmt->execute([$title, $file]);
            $addedCount++;
        }
    }

    // 2. Автоматическая очистка: удаляем из базы и стираем миниатюры на диске
    $dbVideos = $pdo->query("SELECT id, file_name FROM videos")->fetchAll();
    $deletedCount = 0;
    foreach ($dbVideos as $dbVideo) {
        if (!in_array($dbVideo['file_name'], $diskFiles)) {
            // Удаляем файл миниатюры, если он существует
            $fileNameNoExt = pathinfo($dbVideo['file_name'], PATHINFO_FILENAME);
            $thumbFile = __DIR__ . '/../storage/thumbnails/' . $fileNameNoExt . '.jpg';
            if (file_exists($thumbFile)) {
                unlink($thumbFile);
            }

            // Удаляем запись из базы данных
            $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
            $stmt->execute([$dbVideo['id']]);
            $deletedCount++;
        }
    }


    $message = "Синхронизация завершена! Добавлено видео: $addedCount. Удалено отсутствующих записей: $deletedCount.";
}
?>

<div class="container" style="max-w: 56rem; margin-left: auto; margin-right: auto;">
    <div class="bg-white p-8 rounded-md shadow-sm border border-gray-200">
        <h2 class="text-2xl font-bold mb-4 text-gray-700">Синхронизация локального хранилища</h2>
        <p class="text-gray-600 mb-6 text-sm" style="line-height: 1.5rem;">
            Положите ваши видеоуроки в формате <code style="background: #f1f5f9; padding: 2px 6px; color: #dc2626; border-radius: 4px;">.mp4</code> в папку <code style="background: #f1f5f9; padding: 2px 6px; color: #1e293b; border-radius: 4px;">storage/videos/</code> вашего проекта. Нажмите кнопку ниже, чтобы система автоматически создала для них карточки обучения или удалила старые записи из базы.
        </p>

        <?php if ($message): ?>
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-size: 0.875rem; font-medium">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <button type="submit" name="sync" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-md font-medium text-sm transition cursor-pointer border-none shadow-sm" style="display: inline-block;">
                🔄 Найти и обновить видеофайлы
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
