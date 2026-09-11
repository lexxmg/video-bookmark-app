<?php
// public/watch.php
$pageTitle = "Просмотр и изучение материала";
include __DIR__ . '/includes/header.php';

$videoId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->execute([$videoId]);
$video = $stmt->fetch();

if (!$video) {
    echo '<div class="max-w-7xl mx-auto px-6 py-8 text-center"><p class="text-gray-500">Видео не найдено.</p><a href="index.php" class="text-blue-600 underline">На главную</a></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM bookmarks WHERE video_id = ? ORDER BY timestamp ASC");
$stmt->execute([$videoId]);
$bookmarks = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-6 py-8 w-full box-border">
    <!-- Две колонки на чистом Tailwind v4 (Слева плеер, справа sidebar) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <!-- Левая колонка: Видеоплеер -->
        <div class="lg:col-span-2 bg-white p-4 rounded-xl border border-gray-200 shadow-xs">
            <video id="videoPlayer" controls class="w-full rounded-lg bg-black aspect-video" data-saved-volume="<?= (float)($video['volume'] ?? 1.0) ?>">
                <source src="/storage/videos/<?= rawurlencode($video['file_name']) ?>" type="video/mp4">
                Ваш браузер не поддерживает встроенный HTML5 плеер.
            </video>
            <h3 class="text-xl font-extrabold mt-4 text-gray-800 tracking-tight"><?= htmlspecialchars($video['title']) ?></h3>
        </div>

        <!-- Правая колонка: Скроллируемый список меток -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-xs flex flex-col h-[500px] box-border">
            <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4 shrink-0">
                <h3 class="font-bold text-gray-800 text-lg">Метки таймкодов</h3>
                <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-xs font-semibold shadow-xs transition cursor-pointer border-none">
                    + Поставить метку
                </button>
            </div>

            <!-- Список меток -->
            <div id="bookmarksList" class="flex-1 overflow-y-auto pr-1">
                <?php if (empty($bookmarks)): ?>
                    <p id="noBookmarksText" class="text-gray-400 text-center pt-8 text-sm">У этого видео пока нет меток.</p>
                <?php endif; ?>
                
                <?php foreach ($bookmarks as $b): ?>
                    <div class="bookmark-item flex justify-between items-center p-3 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg mb-3 transition" id="bookmark-row-<?= $b['id'] ?>" data-timestamp="<?= $b['timestamp'] ?>">
                        <button onclick="goToTime(<?= $b['timestamp'] ?>)" class="bg-none border-none text-left text-blue-600 hover:text-blue-800 font-bold cursor-pointer p-0 flex-1 text-sm flex items-center">
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-md mr-3 shrink-0">
                                <?= sprintf('%02d:%02d', floor($b['timestamp'] / 60), (int)$b['timestamp'] % 60) ?>
                            </span>
                            <span id="title-text-<?= $b['id'] ?>" class="break-all"><?= htmlspecialchars($b['title']) ?></span>
                        </button>
                        <div class="flex gap-3 ml-2 shrink-0">
                            <button onclick="editBookmark(<?= $b['id'] ?>)" class="bg-none border-none text-gray-400 hover:text-yellow-600 text-xs cursor-pointer p-0">Ред.</button>
                            <button onclick="deleteBookmark(<?= $b['id'] ?>)" class="bg-none border-none text-gray-400 hover:text-red-600 text-xs cursor-pointer p-0">Уд.</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Нативная модалка Tailwind v4: Изначально СВЕРНУТА классом hidden -->
<div id="bookmarkModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 box-border">
    <div class="bg-white p-6 rounded-xl border border-gray-200 max-w-md w-full shadow-xl box-border">
        <h3 id="modalTitle" class="text-xl font-extrabold mb-4 text-gray-800 m-0 tracking-tight">Новый фрагмент</h3>
        <input type="hidden" id="bookmarkId">
        <input type="hidden" id="bookmarkTime">
        
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Название метки:</label>
        <input type="text" id="bookmarkTitleInput" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm mb-5 box-border outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10" placeholder="Например: Разбор грамматики, Новые слова...">
        
        <div class="flex justify-end gap-3">
            <button onclick="closeModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold border-none cursor-pointer transition">Отмена</button>
            <button onclick="saveBookmark()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold border-none cursor-pointer transition">Сохранить</button>
        </div>
    </div>
</div>

<script>
    const player = document.getElementById('videoPlayer');
    const modal = document.getElementById('bookmarkModal');
    const videoId = <?= $videoId ?>;

    function goToTime(seconds) {
        player.currentTime = seconds;
        player.play();
    }

    function openAddModal() {
        player.pause();
        const currentTime = player.currentTime;
        document.getElementById('modalTitle').innerText = "Добавить метку на " + formatTime(currentTime);
        document.getElementById('bookmarkId').value = "";
        document.getElementById('bookmarkTime').value = currentTime;
        document.getElementById('bookmarkTitleInput').value = "";
        modal.classList.remove('hidden');
        document.getElementById('bookmarkTitleInput').focus();
    }

    function editBookmark(id) {
        const currentTitle = document.getElementById('title-text-' + id).innerText;
        document.getElementById('modalTitle').innerText = "Изменить название фрагмента";
        document.getElementById('bookmarkId').value = id;
        document.getElementById('bookmarkTitleInput').value = currentTitle;
        modal.classList.remove('hidden');
        document.getElementById('bookmarkTitleInput').focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    function formatTime(seconds) {
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = Math.floor(seconds % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    }

    function saveBookmark() {
        const id = document.getElementById('bookmarkId').value;
        const title = document.getElementById('bookmarkTitleInput').value.trim();
        const timestamp = parseFloat(document.getElementById('bookmarkTime').value);

        if (!title) return alert('Пожалуйста, введите название метки!');

        const formData = new FormData();
        if (id) {
            formData.append('action', 'edit_bookmark');
            formData.append('id', id);
        } else {
            formData.append('action', 'add_bookmark');
            formData.append('video_id', videoId);
            formData.append('timestamp', timestamp);
        }
        formData.append('title', title);

        fetch('api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success || data === true) {
                closeModal();
                if (id) {
                    document.getElementById('title-text-' + id).innerText = title;
                } else {
                    location.reload(); // Перезагружаем для нативной сортировки SQLite
                }
            } else {
                alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
            }
        });
    }

    function deleteBookmark(id) {
        if (!confirm('Вы уверены, что хотите удалить эту метку?')) return;
        const formData = new FormData();
        formData.append('action', 'delete_bookmark');
        formData.append('id', id);
        fetch('api.php', { method: 'POST', body: formData }).then(() => document.getElementById('bookmark-row-' + id).remove());
    }

    // Пробел
    window.addEventListener('keydown', function(e) {
        if (document.activeElement === document.getElementById('bookmarkTitleInput')) return;
        if (e.code === 'Space' || e.keyCode === 32) {
            e.preventDefault();
            if (player.paused) player.play(); else player.pause();
        }
    });

    // Громкость в БД
    const defaultVolume = player.getAttribute('data-saved-volume');
    if (defaultVolume !== null) player.volume = parseFloat(defaultVolume);
    let volumeTimeout;
    player.addEventListener('volumechange', function() {
        clearTimeout(volumeTimeout);
        volumeTimeout = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'save_volume');
            formData.append('video_id', videoId);
            formData.append('volume', player.volume);
            fetch('api.php', { method: 'POST', body: formData });
        }, 400);
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
