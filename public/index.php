<?php
$pageTitle = "Главная — Мои Видеофайлы";
include __DIR__ . '/includes/header.php';

$videos = $pdo->query("SELECT * FROM videos ORDER BY id DESC")->fetchAll();
?>

<div class="container">
    <h2 class="page-title">Доступные материалы для изучения</h2>

    <?php if (empty($videos)): ?>
        <div class="admin-box" style="text-align: center;">
            <p style="color: #6b7280; margin-bottom: 1rem;">В каталоге пока нет видеофайлов.</p>
            <a href="admin.php" style="color: #2563eb; font-weight: 600; text-decoration: none;">Перейти в админку для синхронизации файлов →</a>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($videos as $v): ?>
                <div class="card" id="video-card-<?= $v['id'] ?>">
                    
                    <!-- КЛИКАБЕЛЬНЫЙ БЛОК ПРЕВЬЮ -->
                    <a href="watch.php?id=<?= $v['id'] ?>" class="card-preview" style="display: block; position: relative; background: #000; height: 160px; overflow: hidden;" title="Нажмите, чтобы открыть плеер">
                        
                        <?php if ((int)$v['has_thumbnail'] === 1): ?>
                            <!-- Изменен путь: картинка берется из папки thumbnails -->
                            <?php $imgName = pathinfo($v['file_name'], PATHINFO_FILENAME) . '.jpg'; ?>
                            <img src="/storage/thumbnails/<?= rawurlencode($imgName) ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Превью">
                        <?php else: ?>
                            <canvas id="canvas-<?= $v['id'] ?>" width="320" height="180" style="width: 100%; height: 100%; object-fit: cover; display: none;"></canvas>
                            <div id="loader-<?= $v['id'] ?>" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #fff;">🎬</div>
                        <?php endif; ?>

                    </a>

                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 1rem;">
                            <h3 class="card-title" id="video-title-<?= $v['id'] ?>" style="margin: 0; font-size: 1rem; line-height: 1.4;" title="<?= htmlspecialchars($v['title']) ?>">
                                <?= htmlspecialchars($v['title']) ?>
                            </h3>
                            <button onclick="openEditVideoModal(<?= $v['id'] ?>)" style="background: none; border: none; color: #9ca3af; cursor: pointer; padding: 2px; font-size: 0.9rem;" class="btn-action edit" title="Переименовать">✏️</button>
                        </div>
                        <a href="watch.php?id=<?= $v['id'] ?>" class="btn">🎬 Начать просмотр</a>
                    </div>

                    <!-- Скрытый плеер генерируется только если картинки еще нет в базе -->
                    <?php if ((int)$v['has_thumbnail'] !== 1): ?>
                        <video id="thumb-video-<?= $v['id'] ?>" data-id="<?= $v['id'] ?>" data-src="/storage/videos/<?= rawurlencode($v['file_name']) ?>" preload="none" muted style="display: none;"></video>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Модалка переименования видео -->
<div id="editVideoModal" class="modal hidden">
    <div class="modal-content">
        <h3 class="text-xl font-bold mb-4 text-gray-800" style="margin-top: 0; margin-bottom: 1rem;">Переименовать видео</h3>
        <input type="hidden" id="editVideoId">
        
        <label style="font-size: 0.875rem; color: #4b5563; font-weight: 500;">Название плитки:</label>
        <input type="text" id="editVideoTitleInput" class="input-field">
        
        <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
            <button onclick="closeEditVideoModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md font-medium border-none cursor-pointer">Отмена</button>
            <button onclick="saveVideoTitle()" class="bg-blue-600 text-white px-4 py-2 rounded-md font-medium border-none cursor-pointer">Сохранить</button>
        </div>
    </div>
</div>

<script>
// --- УМНАЯ ОЧЕРЕДЬ ГЕНЕРАЦИИ И КЭШИРОВАНИЯ КАДРОВ ---
document.addEventListener("DOMContentLoaded", function() {
    const queue = [];
    
    // Собираем в очередь ТОЛЬКО те видео, у которых еще нет готовой миниатюры
    <?php foreach ($videos as $v): ?>
    <?php if ((int)$v['has_thumbnail'] !== 1): ?>
    queue.push({
        id: <?= $v['id'] ?>,
        video: document.getElementById('thumb-video-<?= $v['id'] ?>'),
        canvas: document.getElementById('canvas-<?= $v['id'] ?>'),
        loader: document.getElementById('loader-<?= $v['id'] ?>')
    });
    <?php endif; ?>
    <?php endforeach; ?>

    function processNext() {
        if (queue.length === 0) return;

        const current = queue.shift();
        current.video.src = current.video.getAttribute('data-src');
        current.video.load();

        current.video.onloadedmetadata = function() {
            current.video.currentTime = 2; // Берем красивый кадр на 2-й секунде
        };

        current.video.onseeked = function() {
            try {
                const ctx = current.canvas.getContext('2d');
                ctx.drawImage(current.video, 0, 0, current.canvas.width, current.canvas.height);
                current.canvas.style.display = 'block';
                current.loader.style.display = 'none';

                // Получаем снимок в виде текстовой Base64-строки JPEG высокого качества
                const base64Image = current.canvas.toDataURL('image/jpeg', 0.85);

                // Отправляем картинку на бэкенд для записи в файл навсегда
                const formData = new FormData();
                formData.append('action', 'save_thumbnail');
                formData.append('id', current.id);
                formData.append('image', base64Image);

                fetch('api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        console.log("Миниатюра для видео #" + current.id + " успешно сохранена на сервере!");
                    }
                });

            } catch (e) {
                console.error("Ошибка сохранения кадра:", e);
            }

            current.video.src = "";
            current.video.load();
            current.video.remove();

            setTimeout(processNext, 50); 
        };
    }

    processNext();
});

// --- ЛОГИКА РЕДАКТИРОВАНИЯ НАЗВАНИЯ ---
const videoModal = document.getElementById('editVideoModal');

function openEditVideoModal(id) {
    const currentTitle = document.getElementById('video-title-' + id).innerText;
    document.getElementById('editVideoId').value = id;
    document.getElementById('editVideoTitleInput').value = currentTitle.trim();
    videoModal.classList.remove('hidden');
    document.getElementById('editVideoTitleInput').focus();
}

function closeEditVideoModal() {
    videoModal.classList.add('hidden');
}

function saveVideoTitle() {
    const id = document.getElementById('editVideoId').value;
    const title = document.getElementById('editVideoTitleInput').value.trim();

    if (!title) return alert('Введите название!');

    const formData = new FormData();
    formData.append('action', 'edit_video_title');
    formData.append('id', id);
    formData.append('title', title);

    fetch('api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('video-title-' + id).innerText = title;
            closeEditVideoModal();
        } else {
            alert('Ошибка: ' + data.error);
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
