<?php
// public/admin.php
$pageTitle = "Панель администратора";
include __DIR__ . '/includes/header.php';

$videosDir = __DIR__ . '/../storage/videos/';
$message = '';
$error = '';

// --- ЛОГИКА ЗАГРУЗКИ ФАЙЛА ЧЕРЕЗ БРАУЗЕР ---
if (isset($_FILES['video_file'])) {
    $file = $_FILES['video_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'mp4') {
            $error = "Разрешены только файлы в формате .mp4";
        } else {
            if (!file_exists($videosDir)) {
                mkdir($videosDir, 0777, true);
            }

            // Очищаем имя файла от опасных символов, сохраняя пробелы
            $cleanName = preg_replace('/[^\w\s\d\.\-_а-яА-ЯёЁ]/u', '', $file['name']);
            $targetPath = $videosDir . $cleanName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Сразу регистрируем файл в базе данных
                $title = pathinfo($cleanName, PATHINFO_FILENAME);
                try {
                    $stmt = $pdo->prepare("INSERT OR IGNORE INTO videos (title, file_name) VALUES (?, ?)");
                    $stmt->execute([$title, $cleanName]);
                    $message = "Файл «{$cleanName}» успешно загружен и добавлен в базу!";
                } catch (PDOException $e) {
                    $error = "Файл загружен, но ошибка БД: " . $e->getMessage();
                }
            } else {
                $error = "Не удалось переместить файл в папку хранения. Проверьте права Docker.";
            }
        }
    } else {
        $error = "Ошибка при загрузке файла. Возможно, он превышает лимит сервера.";
    }
}

// --- СИНХРОНИЗАЦИЯ С КОНТРОЛЕМ МИНИАТЮР («ЗАЩИТА ОТ ДУРАКА») ---
if (isset($_POST['sync'])) {
    $diskFiles = [];
    if (is_dir($videosDir)) {
        $files = scandir($videosDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && $file !== '.gitkeep' && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'mp4') {
                $diskFiles[] = $file;
            }
        }
    }

    // 1. Добавляем новые файлы с диска в БД
    $addedCount = 0;
    foreach ($diskFiles as $file) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE file_name = ?");
        $stmt->execute([$file]);
        if ($stmt->fetchColumn() == 0) {
            $title = pathinfo($file, PATHINFO_FILENAME);
            $stmt = $pdo->prepare("INSERT INTO videos (title, file_name) VALUES (?, ?)");
            $stmt->execute([$title, $file]);
            $addedCount++;
        }
    }

    // 2. Автоматическая очистка утерянных видео и сброс удаленных вручную миниатюр
    $dbVideos = $pdo->query("SELECT id, file_name, has_thumbnail FROM videos")->fetchAll();
    $deletedCount = 0;
    foreach ($dbVideos as $dbVideo) {
        $fileNameNoExt = pathinfo($dbVideo['file_name'], PATHINFO_FILENAME);
        $thumbFile = __DIR__ . '/../storage/thumbnails/' . $fileNameNoExt . '.jpg';

        if (!in_array($dbVideo['file_name'], $diskFiles)) {
            // Если видео физически удалено с диска — стираем его миниатюру и запись в БД
            if (file_exists($thumbFile)) {
                unlink($thumbFile);
            }
            $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
            $stmt->execute([$dbVideo['id']]);
            $deletedCount++;
        } else {
            // ЗАЩИТА ОТ ДУРАКА: Если видео на месте, но картинку .jpg стерли руками — сбрасываем флаг в БД
            if ((int)$dbVideo['has_thumbnail'] === 1 && !file_exists($thumbFile)) {
                $stmt = $pdo->prepare("UPDATE videos SET has_thumbnail = 0 WHERE id = ?");
                $stmt->execute([$dbVideo['id']]);
            }
        }
    }
    $message = "Синхронизация завершена! Добавлено новых видео: $addedCount. Удалено мусорных записей: $deletedCount.";
}

// Получаем актуальный список видео для таблицы управления
$allVideos = $pdo->query("SELECT * FROM videos ORDER BY id DESC")->fetchAll();
?>

<div class="container" style="max-w: 64rem; margin-left: auto; margin-right: auto;">

    <!-- Сообщения об успехе или ошибке -->
    <?php if ($message): ?>
        <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-size: 0.875rem;">
            ✅ <?= $message ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-size: 0.875rem;">
            ❌ <?= $error ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">

        <!-- БЛОК 1: ЗАГРУЗКА НОВОГО ВИДЕО -->
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
            <h3 class="text-xl font-bold mb-4 text-gray-700 m-0">Загрузить новое видео</h3>

            <form method="POST" enctype="multipart/form-data" id="uploadForm" class="flex flex-col gap-4">
                <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">Выберите файл (.mp4):</span>

                <!-- КРАСИВАЯ КЛИКАБЕЛЬНАЯ ЗОНА С СЕНСОРНЫМ КУРСОРOM -->
                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 hover:border-blue-500 transition duration-150 p-4 box-border text-center">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <span class="text-3xl mb-2">📁</span>
                        <p class="text-sm text-gray-600 font-semibold m-0" id="file-select-text">Нажмите для выбора файла</p>
                        <p class="text-xs text-gray-400 m-0 mt-1">Максимальный размер: 2 ГБ</p>
                    </div>
                    <!-- Сам скрытый инпут, который активируется кликом по лейблу сверху -->
                    <input type="file" name="video_file" id="video_file_input" accept="video/mp4" required class="hidden">
                </label>

                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg font-semibold text-sm shadow-sm hover:shadow-md transition duration-150 cursor-pointer border-none mt-2">
                    🚀 Начать загрузку на сервер
                </button>
            </form>

            <div id="progressStatus" class="hidden mt-4 text-sm text-blue-600 font-semibold">
                ⏳ Файл отправляется на сервер, пожалуйста, не закрывайте вкладку...
            </div>
        </div>


        <!-- БЛОК 2: СИНХРОНИЗАЦИЯ С ПАПКОЙ -->
        <div class="bg-white p-6 rounded-md shadow-sm border border-gray-200" style="display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <h3 class="text-xl font-bold mb-3 text-gray-700" style="margin-top:0;">Синхронизация папки</h3>
                <p class="text-gray-500 text-xs" style="line-height:1.3rem; margin-bottom:1rem;">
                    Если вы залили видеофайлы напрямую на хостинг или в Docker-папку через FTP/SFTP, нажмите эту кнопку, чтобы обновить базу данных.
                </p>
            </div>
            <form method="POST">
                <button type="submit" name="sync" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium text-sm transition cursor-pointer border-none shadow-sm w-full text-center">
                    🔄 Синхронизировать файлы на диске
                </button>
            </form>
        </div>
    </div>

    <!-- БЛОК 3: ТАБЛИЦА УПРАВЛЕНИЯ ФАЙЛАМИ -->
    <div class="bg-white p-6 rounded-md shadow-sm border border-gray-200">
        <h3 class="text-xl font-bold mb-4 text-gray-700" style="margin-top:0;">Все загруженные видеоматериалы (<?= count($allVideos) ?>)</h3>

        <?php if (empty($allVideos)): ?>
            <p style="color:#9ca3af; text-align:center; padding: 2rem 0; font-size:0.875rem;">В базе данных пока нет ни одного видео.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f3f4f6; color: #4b5563; font-weight: 600;">
                            <th style="padding: 0.75rem 0.5rem;">Название на плитке</th>
                            <th style="padding: 0.75rem 0.5rem;">Имя файла на диске</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: center;">Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allVideos as $v): ?>
                            <tr id="video-row-<?= $v['id'] ?>" style="border-bottom: 1px solid #f3f4f6;" class="hover:bg-gray-50">
                                <td style="padding: 0.75rem 0.5rem; font-weight: 600; color: #1f2937;"><?= htmlspecialchars($v['title']) ?></td>
                                <td style="padding: 0.75rem 0.5rem; color: #6b7280; font-family: monospace;"><?= htmlspecialchars($v['file_name']) ?></td>
                                <td style="padding: 0.75rem 0.5rem; text-align: center; display: flex; justify-content: center; gap: 0.5rem;">
                                    <!-- Кнопка обнуления просмотров -->
                                    <button onclick="resetVideoViews(<?= $v['id'] ?>, '<?= htmlspecialchars($v['title'], ENT_QUOTES) ?>')" class="bg-amber-100 hover:bg-amber-200 text-amber-700 px-3 py-1 rounded border-none font-medium text-xs cursor-pointer transition">
                                        🔄 Сбросить просмотры
                                    </button>

                                    <button onclick="deleteVideoCompletely(<?= $v['id'] ?>, '<?= htmlspecialchars($v['title'], ENT_QUOTES) ?>')" class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded border-none font-medium text-xs cursor-pointer transition">
                                        🗑️ Удалить
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Индикация отправки формы
    document.getElementById('uploadForm').addEventListener('submit', function() {
        document.getElementById('progressStatus').style.display = 'block';
    });

    // Асинхронное полное удаление видеоурока
    function deleteVideoCompletely(id, title) {
        if (!confirm("Вы действительно хотите НАВСЕГДА удалить видео «" + title + "»?\n\nБудет физически удален .mp4 файл, превью .jpg и ВСЕ созданные закладки таймкодов!")) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_video_completely');
        formData.append('id', id);

        fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('video-row-' + id).remove();
                } else {
                    alert("Ошибка при удалении: " + data.error);
                }
            })
            .catch(err => alert("Ошибка сети при отправке запроса в API"));
    }

    // Динамическое отображение имени выбранного файла в админке
    document.getElementById('video_file_input').addEventListener('change', function(e) {
        const fileName = e.target.files[0] ? e.target.files[0].name : "Нажмите для выбора файла";
        document.getElementById('file-select-text').innerText = fileName;
        document.getElementById('file-select-text').classList.add('text-blue-600');
    });

    // Асинхронное обнуление счетчика просмотров
    function resetVideoViews(id, title) {
        if (!confirm("Вы действительно хотите обнулить счетчик просмотров для видео «" + title + "»?")) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'reset_views');
        formData.append('video_id', id);

        fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Счетчик просмотров успешно обнулен!");
                    // Если вы захотите, чтобы количество просмотров динамически обновлялось в интерфейсе админки,
                    // здесь можно перезагрузить страницу: window.location.reload();
                } else {
                    alert("Ошибка при обнулении: " + data.error);
                }
            })
            .catch(err => alert("Ошибка сети при отправке запроса в API"));
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>